// lib/core/network/api_client.dart
import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kReleaseMode;
import 'package:http/http.dart' as http;
import 'package:bisklet/core/config/app_config.dart';

class ApiGetResponse {
  ApiGetResponse({
    required this.statusCode,
    required this.headers,
    required this.body,
  });

  final int statusCode;
  final Map<String, String> headers;
  final Map<String, dynamic> body;
}

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.payload});
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? payload;
  @override
  String toString() => 'ApiException($statusCode): $message';
}

class ApiClient {
  ApiClient({
    required this.baseUrl,
    this.authToken,
    String? Function()? authTokenProvider,
  }) : _authTokenProvider = authTokenProvider {
    final uri = _validateDestination(baseUrl);
    if ((uri.path.isNotEmpty && uri.path != '/') || uri.hasQuery) {
      throw ApiException('Invalid API base URL');
    }
  }

  final String baseUrl;
  String? authToken;
  final String? Function()? _authTokenProvider;
  static const Duration _timeout = Duration(seconds: 20);
  static const int maxResponseBytes = 2 * 1024 * 1024;
  static const int maxJsonDepth = 64;

  static Uri _validateDestination(String value) {
    final uri = Uri.tryParse(value);
    final approved = Uri.tryParse(AppConfig.apiBaseUrl);
    if (uri == null || approved == null) {
      throw ApiException('Unapproved API destination');
    }

    // Public example builds may use a loopback development server.
    // Remote destinations must use HTTPS, and all destinations must
    // match the compile-time approved origin exactly.
    final developmentHost = approved.host == 'localhost' ||
        approved.host == '127.0.0.1' ||
        approved.host == '::1' ||
        approved.host == '10.0.2.2'; // Android emulator bridge to host loopback.
    final validScheme = approved.scheme == 'https' ||
        (!kReleaseMode && approved.scheme == 'http' && developmentHost);
    if (!validScheme ||
        (approved.path.isNotEmpty && approved.path != '/') ||
        approved.hasQuery ||
        approved.hasFragment ||
        approved.userInfo.isNotEmpty ||
        uri.scheme != approved.scheme ||
        uri.host != approved.host ||
        uri.port != approved.port ||
        uri.userInfo.isNotEmpty ||
        uri.hasFragment ||
        value.contains('\\')) {
      throw ApiException('Unapproved API destination');
    }
    return uri;
  }

  Uri _requestUri(String path) {
    final relative = Uri.tryParse(path);
    if (relative == null ||
        relative.hasScheme ||
        relative.hasAuthority ||
        !path.startsWith('/') ||
        path.startsWith('//')) {
      throw ApiException('Invalid API request path');
    }
    return _validateDestination(
      Uri.parse(baseUrl).resolveUri(relative).toString(),
    );
  }

  Future<http.Response> _send(
    String method,
    Uri uri,
    Map<String, String> headers, {
    Map<String, dynamic>? body,
  }) async {
    final request = http.Request(method, uri)
      ..followRedirects = false
      ..headers.addAll(headers);
    if (body != null) request.body = jsonEncode(body);
    final client = http.Client();
    try {
      final response = await client.send(request);
      if (response.statusCode >= 300 &&
          response.statusCode < 400 &&
          response.statusCode != 304) {
        throw ApiException(
          'API redirects are not allowed',
          statusCode: response.statusCode,
        );
      }
      if ((response.contentLength ?? 0) > maxResponseBytes) {
        await response.stream.listen(null).cancel();
        throw ApiException('API response exceeds the size limit');
      }
      final bytes = BytesBuilder(copy: false);
      await for (final chunk in response.stream) {
        if (chunk.length > maxResponseBytes - bytes.length) {
          throw ApiException('API response exceeds the size limit');
        }
        bytes.add(chunk);
      }
      return http.Response.bytes(
        bytes.takeBytes(),
        response.statusCode,
        headers: response.headers,
        request: response.request,
        isRedirect: response.isRedirect,
        persistentConnection: response.persistentConnection,
        reasonPhrase: response.reasonPhrase,
      );
    } finally {
      client.close();
    }
  }

  Map<String, String> _defaultHeaders() {
    final h = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    final resolvedAuthToken = (_authTokenProvider?.call() ?? authToken)?.trim();
    if (resolvedAuthToken != null && resolvedAuthToken.isNotEmpty) {
      h['Authorization'] = 'Bearer $resolvedAuthToken';
    }
    return h;
  }

  Map<String, String> _requestHeaders(Map<String, String>? extraHeaders) {
    const protectedHeaders = {
      'authorization',
      'proxy-authorization',
      'host',
      'cookie',
      'content-type',
      'accept',
      'content-length',
      'transfer-encoding',
      'content-security-policy',
      'content-security-policy-report-only',
      'x-content-type-options',
    };
    for (final name in extraHeaders?.keys ?? const <String>[]) {
      if (protectedHeaders.contains(name.trim().toLowerCase())) {
        throw ApiException('Custom headers cannot override protected headers');
      }
    }
    // Apply client-owned headers last, after validating caller headers.
    return {...?extraHeaders, ..._defaultHeaders()};
  }

  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> body, {
    Map<String, String>? extraHeaders,
  }) async {
    final uri = _requestUri(path);
    final headers = _requestHeaders(extraHeaders);

    final http.Response resp;

    try {
      resp = await _send('POST', uri, headers, body: body).timeout(
        _timeout,
        onTimeout: () => throw ApiException('Request timed out'),
      );
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException(_networkErrorMessage(e));
    }

    final Map<String, dynamic> decoded = _decodeBodyAsMap(resp.body);

    if (resp.statusCode >= 200 && resp.statusCode < 300) {
      return decoded;
    }

    final msg =
        (decoded['message'] ??
                decoded['error'] ??
                decoded['errors'] ??
                'Request failed')
            .toString();

    throw ApiException(msg, statusCode: resp.statusCode, payload: decoded);
  }

  // helper for GET if needed
  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, String>? extraHeaders,
  }) async {
    final response = await getWithMetadata(path, extraHeaders: extraHeaders);
    return response.body;
  }

  Future<ApiGetResponse> getWithMetadata(
    String path, {
    Map<String, String>? extraHeaders,
    bool allowNotModified = false,
  }) async {
    final uri = _requestUri(path);
    final headers = _requestHeaders(extraHeaders);
    final http.Response resp;

    try {
      resp = await _send('GET', uri, headers).timeout(
        _timeout,
        onTimeout: () => throw ApiException('Request timed out'),
      );
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException(_networkErrorMessage(e));
    }

    final Map<String, dynamic> decoded = _decodeBodyAsMap(resp.body);

    if (resp.statusCode >= 200 && resp.statusCode < 300) {
      return ApiGetResponse(
        statusCode: resp.statusCode,
        headers: _normalizeHeaders(resp.headers),
        body: decoded,
      );
    }

    if (allowNotModified && resp.statusCode == 304) {
      return ApiGetResponse(
        statusCode: resp.statusCode,
        headers: _normalizeHeaders(resp.headers),
        body: decoded,
      );
    }

    final msg = (decoded['message'] ?? decoded['error'] ?? 'Request failed')
        .toString();
    throw ApiException(msg, statusCode: resp.statusCode, payload: decoded);
  }

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, String>? extraHeaders,
  }) async {
    final uri = _requestUri(path);
    final headers = _requestHeaders(extraHeaders);
    final http.Response resp;

    try {
      resp = await _send('DELETE', uri, headers).timeout(
        _timeout,
        onTimeout: () => throw ApiException('Request timed out'),
      );
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException(_networkErrorMessage(e));
    }

    final Map<String, dynamic> decoded = _decodeBodyAsMap(resp.body);

    if (resp.statusCode >= 200 && resp.statusCode < 300) {
      return decoded;
    }

    final msg = (decoded['message'] ?? decoded['error'] ?? 'Request failed')
        .toString();
    throw ApiException(msg, statusCode: resp.statusCode, payload: decoded);
  }

  Future<Map<String, dynamic>> patch(
    String path,
    Map<String, dynamic> body, {
    Map<String, String>? extraHeaders,
  }) async {
    final uri = _requestUri(path);
    final headers = _requestHeaders(extraHeaders);
    final http.Response resp;

    try {
      resp = await _send('PATCH', uri, headers, body: body).timeout(
        _timeout,
        onTimeout: () => throw ApiException('Request timed out'),
      );
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException(_networkErrorMessage(e));
    }

    final Map<String, dynamic> decoded = _decodeBodyAsMap(resp.body);
    if (resp.statusCode >= 200 && resp.statusCode < 300) {
      return decoded;
    }

    final msg = (decoded['message'] ?? decoded['error'] ?? 'Request failed')
        .toString();
    throw ApiException(msg, statusCode: resp.statusCode, payload: decoded);
  }

  /// Safely decode a response body as map.
  /// If the body is empty returns an empty map.
  /// If the JSON is a list or primitive, it will be wrapped in a data key.
  Map<String, dynamic> _decodeBodyAsMap(String body) {
    if (body.isEmpty) return <String, dynamic>{};
    _validateJsonDepth(body);
    final dynamic raw;
    try {
      raw = jsonDecode(body);
    } on FormatException {
      throw ApiException('Invalid JSON response');
    }
    if (raw is Map) {
      // ensure keys/values are properly typed as String -> dynamic
      return Map<String, dynamic>.from(raw);
    }
    // for lists/primitives, wrap into a 'data' key so callers still get a Map
    return <String, dynamic>{'data': raw};
  }

  // Scan before jsonDecode so deeply nested input never reaches the parser.
  void _validateJsonDepth(String body) {
    var depth = 0;
    var inString = false;
    var escaped = false;
    for (final code in body.codeUnits) {
      if (inString) {
        if (escaped) {
          escaped = false;
        } else if (code == 0x5c) {
          escaped = true;
        } else if (code == 0x22) {
          inString = false;
        }
      } else if (code == 0x22) {
        inString = true;
      } else if (code == 0x7b || code == 0x5b) {
        if (++depth > maxJsonDepth) {
          throw ApiException('API response exceeds the JSON nesting limit');
        }
      } else if (code == 0x7d || code == 0x5d) {
        if (--depth < 0) throw ApiException('Invalid JSON response');
      }
    }
  }

  Map<String, String> _normalizeHeaders(Map<String, String> headers) {
    final normalized = <String, String>{};
    for (final entry in headers.entries) {
      normalized[entry.key.toLowerCase()] = entry.value;
    }
    return normalized;
  }

  String _networkErrorMessage(Object error) {
    final text = error.toString().toLowerCase();

    if (text.contains('failed host lookup') ||
        text.contains('nodename nor servname') ||
        text.contains('no address associated')) {
      return 'Could not reach Bisklet servers. Check your internet connection and try again.';
    }

    if (text.contains('connection refused') ||
        text.contains('connection reset') ||
        text.contains('network is unreachable')) {
      return 'Bisklet servers are unreachable right now. Please try again.';
    }

    return 'Network request failed. Please try again.';
  }
}
