import 'package:bisklet/core/config/api_endpoints.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final endpoints = <String, String Function(String)>{
    '/api/v1/auth/fyda/result/{id}': ApiEndpoints.authFydaResult,
    '/api/v1/bikes/{id}': ApiEndpoints.bike,
    '/api/v1/stations/{id}': ApiEndpoints.station,
    '/api/v1/rides/{id}': ApiEndpoints.ride,
    '/api/v1/rides/{id}/complete': ApiEndpoints.rideComplete,
    '/api/v1/rides/{id}/payments': ApiEndpoints.ridePayments,
    '/api/v1/rides/{id}/location': ApiEndpoints.rideLocation,
    '/api/v1/notifications/{id}/read': ApiEndpoints.notificationRead,
    '/api/v1/notification-devices/{id}': ApiEndpoints.notificationDevice,
    '/api/v1/locks/{id}/unlock': ApiEndpoints.lockUnlock,
    '/api/v1/bikes/{id}/telemetry': ApiEndpoints.bikeTelemetry,
    '/api/v1/bikes/{id}/damage-reports': ApiEndpoints.bikeDamageReports,
  };

  for (final entry in endpoints.entries) {
    test(
      '${entry.key} preserves supported identifiers and route structure',
      () {
        for (final id in [
          '123',
          '867123456789012',
          '550e8400-e29b-41d4-a716-446655440000',
          'AuthState_abc-XYZ012',
        ]) {
          final path = entry.value(id);
          expect(path, entry.key.replaceFirst('{id}', id));
          final uri = Uri.parse('https://biskilet.com').resolve(path);
          expect(uri.host, 'biskilet.com');
          expect(uri.hasQuery, isFalse);
          expect(uri.hasFragment, isFalse);
          expect(
            uri.pathSegments.where((segment) => segment == id),
            hasLength(1),
          );
        }
      },
    );

    test(
      '${entry.key} rejects path, query, fragment and encoded injection',
      () {
        for (final id in [
          '',
          '.',
          '..',
          '../rides',
          'a/b',
          '/123',
          'a\\b',
          '123?admin=true',
          '123#fragment',
          '%',
          '%2f',
          '%2F',
          '%2e%2e',
          '%252e%252e%252f',
          '..%2frides',
          'https://attacker.example',
          '//attacker.example',
          'id with space',
          ' id',
          'id ',
          'id\n',
          'id\r\nHost: attacker',
          'id\u0000',
          'a+b',
          'a=b',
          'a.b',
          'é',
        ]) {
          expect(
            () => entry.value(id),
            throwsArgumentError,
            reason: 'Accepted identifier: $id',
          );
        }
      },
    );
  }
}
