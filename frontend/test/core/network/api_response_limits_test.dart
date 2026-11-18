import 'dart:convert';

import 'package:bisklet/core/config/app_config.dart';
import 'package:bisklet/core/network/api_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'api_client_test.dart' show invoke;

class StreamingTestClient extends http.BaseClient {
  StreamingTestClient(this.response);
  final http.StreamedResponse response;
  bool closed = false;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async =>
      response;

  @override
  void close() => closed = true;
}

void main() {
  for (final method in ['GET', 'POST', 'PATCH', 'DELETE', 'METADATA']) {
    test(
      '$method rejects oversized advertised responses before reading',
      () async {
        var read = false;
        var cancelled = false;
        Stream<List<int>> body() async* {
          try {
            yield [123, 125];
          } finally {
            cancelled = true;
          }
        }

        final transport = StreamingTestClient(
          http.StreamedResponse(
            body().map((chunk) {
              read = true;
              return chunk;
            }),
            200,
            contentLength: ApiClient.maxResponseBytes + 1,
          ),
        );
        await http.runWithClient(() async {
          await expectLater(
            invoke(
              ApiClient(baseUrl: AppConfig.apiBaseUrl),
              method,
              '/api/v1/rides',
            ),
            throwsA(
              isA<ApiException>().having(
                (e) => e.message,
                'message',
                contains('size limit'),
              ),
            ),
          );
        }, () => transport);
        expect(read, isFalse);
        expect(cancelled, isTrue);
        expect(transport.closed, isTrue);
      },
    );

    for (final advertisedLength in <int?>[null, 2]) {
      test(
        '$method cancels oversized streaming bodies with length $advertisedLength',
        () async {
          var chunksRead = 0;
          var cancelled = false;
          Stream<List<int>> body() async* {
            try {
              for (var i = 0; i < 10; i++) {
                chunksRead++;
                yield List<int>.filled(ApiClient.maxResponseBytes ~/ 2, 32);
              }
            } finally {
              cancelled = true;
            }
          }

          final transport = StreamingTestClient(
            http.StreamedResponse(body(), 500, contentLength: advertisedLength),
          );
          await http.runWithClient(() async {
            await expectLater(
              invoke(
                ApiClient(baseUrl: AppConfig.apiBaseUrl),
                method,
                '/api/v1/rides',
              ),
              throwsA(
                isA<ApiException>().having(
                  (e) => e.message,
                  'message',
                  contains('size limit'),
                ),
              ),
            );
          }, () => transport);
          expect(chunksRead, 3);
          expect(cancelled, isTrue);
          expect(transport.closed, isTrue);
        },
      );
    }

    test('$method rejects excessive JSON nesting before decoding', () async {
      final payload =
          '${'[' * (ApiClient.maxJsonDepth + 1)}0${']' * (ApiClient.maxJsonDepth + 1)}';
      await http.runWithClient(() async {
        await expectLater(
          invoke(
            ApiClient(baseUrl: AppConfig.apiBaseUrl),
            method,
            '/api/v1/rides',
          ),
          throwsA(
            isA<ApiException>().having(
              (e) => e.message,
              'message',
              contains('nesting limit'),
            ),
          ),
        );
      }, () => MockClient((_) async => http.Response(payload, 200)));
    });
  }

  test('accepts a response exactly at the byte limit', () async {
    final payload = '"${'a' * (ApiClient.maxResponseBytes - 2)}"';
    await http.runWithClient(() async {
      final response = await ApiClient(
        baseUrl: AppConfig.apiBaseUrl,
      ).get('/api/v1/rides');
      expect(
        (response['data'] as String).length,
        ApiClient.maxResponseBytes - 2,
      );
    }, () => MockClient((_) async => http.Response(payload, 200)));
  });

  test('counts UTF-8 bytes rather than characters', () async {
    final payload = jsonEncode({
      'data': 'é' * (ApiClient.maxResponseBytes ~/ 2),
    });
    expect(payload.length, lessThan(ApiClient.maxResponseBytes));
    await http.runWithClient(
      () async {
        await expectLater(
          ApiClient(baseUrl: AppConfig.apiBaseUrl).get('/api/v1/rides'),
          throwsA(
            isA<ApiException>().having(
              (e) => e.message,
              'message',
              contains('size limit'),
            ),
          ),
        );
      },
      () => MockClient(
        (_) async => http.Response.bytes(
          utf8.encode(payload),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        ),
      ),
    );
  });

  test(
    'accepts the nesting boundary and ignores escaped string contents',
    () async {
      final value = {
        'text': '${'[{"' * 100}\\escaped"quote',
        'data': [1, true, null],
      };
      final payloads = [
        '${'[' * ApiClient.maxJsonDepth}0${']' * ApiClient.maxJsonDepth}',
        jsonEncode(value),
      ];
      for (final payload in payloads) {
        await http.runWithClient(() async {
          final response = await ApiClient(
            baseUrl: AppConfig.apiBaseUrl,
          ).get('/api/v1/rides');
          final decoded = jsonDecode(payload);
          expect(response, decoded is Map ? decoded : {'data': decoded});
        }, () => MockClient((_) async => http.Response(payload, 200)));
      }
    },
  );

  test('malformed JSON produces a controlled API error', () async {
    for (final payload in ['{"data":', ']}', 'plain text']) {
      await http.runWithClient(() async {
        await expectLater(
          ApiClient(baseUrl: AppConfig.apiBaseUrl).get('/api/v1/rides'),
          throwsA(
            isA<ApiException>().having(
              (e) => e.message,
              'message',
              'Invalid JSON response',
            ),
          ),
        );
      }, () => MockClient((_) async => http.Response(payload, 200)));
    }
  });
}
