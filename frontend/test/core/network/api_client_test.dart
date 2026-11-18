import 'dart:convert';

import 'package:bisklet/core/config/app_config.dart';
import 'package:bisklet/core/network/api_client.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

Future<dynamic> invoke(
  ApiClient client,
  String method,
  String path, {
  Map<String, String>? extraHeaders,
}) {
  switch (method) {
    case 'POST':
      return client.post(path, {'value': 1}, extraHeaders: extraHeaders);
    case 'PATCH':
      return client.patch(path, {'value': 1}, extraHeaders: extraHeaders);
    case 'DELETE':
      return client.delete(path, extraHeaders: extraHeaders);
    case 'METADATA':
      return client.getWithMetadata(path, extraHeaders: extraHeaders);
    default:
      return client.get(path, extraHeaders: extraHeaders);
  }
}

void main() {
  const methods = ['GET', 'POST', 'PATCH', 'DELETE'];

  for (final method in [...methods, 'METADATA']) {
    test(
      '$method rejects protected headers regardless of case or token presence',
      () async {
        var sent = false;
        var tokenRead = false;
        await http.runWithClient(
          () async {
            for (final token in <String?>[null, 'legitimate-token']) {
              final client = ApiClient(
                baseUrl: AppConfig.apiBaseUrl,
                authTokenProvider: () {
                  tokenRead = true;
                  return token;
                },
              );
              for (final name in [
                'Authorization',
                'authorization',
                'AUTHORIZATION',
                'aUtHoRiZaTiOn',
                ' Authorization ',
                'Host',
                'hOsT',
                'Cookie',
                'Proxy-Authorization',
                'Content-Type',
                'content-type',
                'Accept',
                'Content-Length',
                'Transfer-Encoding',
                'Content-Security-Policy',
                'content-security-policy-report-only',
                'X-Content-Type-Options',
              ]) {
                for (final value in ['', 'attacker-controlled']) {
                  await expectLater(
                    invoke(
                      client,
                      method,
                      '/api/v1/rides',
                      extraHeaders: {name: value},
                    ),
                    throwsA(isA<ApiException>()),
                  );
                }
              }
            }
          },
          () => MockClient((request) async {
            sent = true;
            return http.Response('{}', 200);
          }),
        );
        expect(sent, isFalse);
        expect(tokenRead, isFalse);
      },
    );

    test(
      '$method preserves safe headers and client-owned authentication',
      () async {
        var sent = false;
        await http.runWithClient(
          () async {
            final client = ApiClient(
              baseUrl: AppConfig.apiBaseUrl,
              authToken: 'legitimate-token',
            );
            await invoke(
              client,
              method,
              '/api/v1/rides',
              extraHeaders: {
                'If-None-Match': 'version-1',
                'X-Request-ID': 'request-1',
              },
            );
          },
          () => MockClient((request) async {
            sent = true;
            expect(request.headers['Authorization'], 'Bearer legitimate-token');
            expect(
              request.headers['Content-Type'],
              startsWith('application/json'),
            );
            expect(request.headers['Accept'], 'application/json');
            expect(request.headers['If-None-Match'], 'version-1');
            expect(request.headers['X-Request-ID'], 'request-1');
            return http.Response('{}', 200);
          }),
        );
        expect(sent, isTrue);
      },
    );
  }

  test('packaged configuration cannot change the API destination', () {
    final configured = AppConfig.apiBaseUrl;
    dotenv.testLoad(fileInput: 'API_BASE_URL=https://attacker.example');
    expect(AppConfig.apiBaseUrl, configured);
    dotenv.clean();
  });

  for (final url in [
    'http://biskilet.com',
    'https://attacker.example',
    'https://biskilet.com.attacker.example',
    'https://biskilet.com@attacker.example',
    'https://attacker@biskilet.com',
    'https://biskilet.com:8443',
    'https://biskilet.com:80',
    'https://biskilet.com.',
    'https://127.0.0.1',
    'https://biskilet.com/api',
    'https://biskilet.com?destination=attacker',
    'https://biskilet.com#fragment',
    '//biskilet.com',
    'file:///api/v1',
    'not a URL',
    'https://[invalid',
  ]) {
    test('rejects unapproved base URL: $url', () {
      var tokenRead = false;
      expect(
        () => ApiClient(
          baseUrl: url,
          authTokenProvider: () {
            tokenRead = true;
            return 'secret';
          },
        ),
        throwsA(isA<ApiException>()),
      );
      expect(tokenRead, isFalse);
    });
  }

  test(
    'uses the latest token from the token provider for every request',
    () async {
      final authorizationHeaders = <String?>[];
      String? currentToken;
      final client = ApiClient(
        baseUrl: AppConfig.apiBaseUrl,
        authTokenProvider: () => currentToken,
      );
      await http.runWithClient(
        () async {
          await client.get('/api/v1/rides');
          currentToken = 'fresh-token';
          await client.get('/api/v1/rides');
        },
        () => MockClient((request) async {
          authorizationHeaders.add(request.headers['Authorization']);
          return http.Response('{"data": []}', 200);
        }),
      );
      expect(authorizationHeaders, [null, 'Bearer fresh-token']);
    },
  );

  for (final method in methods) {
    test(
      '$method sends credentials only to the configured approved origin',
      () async {
        var sent = false;
        await http.runWithClient(
          () async {
            final client = ApiClient(
              baseUrl: '${AppConfig.apiBaseUrl}/',
              authToken: 'secret',
            );
            await invoke(client, method, '/api/v1/rides?limit=2');
          },
          () => MockClient((request) async {
            sent = true;
            expect(request.method, method);
            expect(request.url.scheme, Uri.parse(AppConfig.apiBaseUrl).scheme);
            expect(request.url.host, Uri.parse(AppConfig.apiBaseUrl).host);
            expect(request.url.port, Uri.parse(AppConfig.apiBaseUrl).port);
            expect(request.url.path, '/api/v1/rides');
            expect(request.url.queryParameters, {'limit': '2'});
            expect(request.headers['Authorization'], 'Bearer secret');
            expect(request.followRedirects, isFalse);
            if (method == 'POST' || method == 'PATCH') {
              expect(jsonDecode(request.body), {'value': 1});
            }
            return http.Response('{}', 200);
          }),
        );
        expect(sent, isTrue);
      },
    );

    test(
      '$method rejects unsafe paths before reading or sending a token',
      () async {
        var tokenRead = false;
        var sent = false;
        await http.runWithClient(
          () async {
            final client = ApiClient(
              baseUrl: AppConfig.apiBaseUrl,
              authTokenProvider: () {
                tokenRead = true;
                return 'secret';
              },
            );
            for (final path in [
              'https://attacker.example/api/v1',
              '//attacker.example/api/v1',
              '/\\attacker.example/api/v1',
              '/api/v1#fragment',
              'api/v1/rides',
            ]) {
              await expectLater(
                invoke(client, method, path),
                throwsA(isA<ApiException>()),
              );
            }
          },
          () => MockClient((request) async {
            sent = true;
            return http.Response('{}', 200);
          }),
        );
        expect(tokenRead, isFalse);
        expect(sent, isFalse);
      },
    );

    for (final status in [301, 302, 303, 307, 308]) {
      test('$method refuses HTTP $status redirects', () async {
        var requests = 0;
        await http.runWithClient(
          () async {
            final client = ApiClient(
              baseUrl: AppConfig.apiBaseUrl,
              authToken: 'secret',
            );
            await expectLater(
              invoke(client, method, '/api/v1/rides'),
              throwsA(
                isA<ApiException>().having(
                  (e) => e.statusCode,
                  'status',
                  status,
                ),
              ),
            );
          },
          () => MockClient((request) async {
            requests++;
            expect(request.followRedirects, isFalse);
            return http.Response(
              '<html>redirect</html>',
              status,
              headers: {'location': 'https://attacker.example/collect'},
            );
          }),
        );
        expect(requests, 1);
      });
    }
  }

  test('GET preserves metadata and optional 304 responses', () async {
    await http.runWithClient(
      () async {
        final client = ApiClient(baseUrl: AppConfig.apiBaseUrl);
        final response = await client.getWithMetadata(
          '/api/v1/rides',
          extraHeaders: {'If-None-Match': 'version-1'},
          allowNotModified: true,
        );
        expect(response.statusCode, 304);
        expect(response.headers['etag'], 'version-1');
        expect(response.body, isEmpty);
      },
      () => MockClient((request) async {
        expect(request.headers['If-None-Match'], 'version-1');
        return http.Response('', 304, headers: {'ETag': 'version-1'});
      }),
    );
  });
}
