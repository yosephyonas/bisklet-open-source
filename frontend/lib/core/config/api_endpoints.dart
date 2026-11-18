// lib/core/config/api_endpoints.dart
class ApiEndpoints {
  // IDs, UUIDs, IMEIs and auth state tokens must remain one path segment.
  static final RegExp _identifierPattern = RegExp(r'^[A-Za-z0-9_-]+$');

  static String _segment(String identifier) {
    if (identifier.isEmpty ||
        _identifierPattern.stringMatch(identifier) != identifier) {
      throw ArgumentError('Invalid API path identifier');
    }
    return Uri.encodeComponent(identifier);
  }

  // Auth
  static const String authFydaAuthorize = '/api/v1/auth/fyda/authorize';
  static String authFydaResult(String state) =>
      '/api/v1/auth/fyda/result/${_segment(state)}';
  static const String authMe = '/api/v1/auth/me';
  static const String authLogout = '/api/v1/auth/logout';
  static const String authLogoutAll = '/api/v1/auth/logout-all';
  static const String authDelete = '/api/v1/auth/delete';

  // Mapbox / Map
  static const String mapConfig = '/api/v1/map/config';
  static const String mapRoutes = '/api/v1/map/routes';

  // Bikes / Rides / Stations
  static const String bikes = '/api/v1/bikes';
  static String bike(String id) => '/api/v1/bikes/${_segment(id)}';

  static const String stations = '/api/v1/stations';
  static String station(String id) => '/api/v1/stations/${_segment(id)}';

  static const String rides = '/api/v1/rides';
  static String ride(String id) => '/api/v1/rides/${_segment(id)}';
  static String rideComplete(String id) =>
      '/api/v1/rides/${_segment(id)}/complete';
  static String ridePayments(String id) =>
      '/api/v1/rides/${_segment(id)}/payments';
  static String rideLocation(String id) =>
      '/api/v1/rides/${_segment(id)}/location';

  // Notifications
  static const String notifications = '/api/v1/notifications';
  static String notificationRead(String id) =>
      '/api/v1/notifications/${_segment(id)}/read';
  static const String notificationsReadAll = '/api/v1/notifications/read-all';
  static const String notificationDevices = '/api/v1/notification-devices';
  static String notificationDevice(String id) =>
      '/api/v1/notification-devices/${_segment(id)}';
  static const String notificationPreferences =
      '/api/v1/notification-preferences';
  static String exclusiveDeals({int limit = 8}) =>
      '/api/v1/notifications?scope=exclusive_deals&limit=$limit';

  // Locks
  static const String lockScan = '/api/v1/locks/scan';
  static String lockUnlock(String imei) =>
      '/api/v1/locks/${_segment(imei)}/unlock';

  // Telemetry / others
  static String bikeTelemetry(String id) =>
      '/api/v1/bikes/${_segment(id)}/telemetry';
  static String bikeDamageReports(String id) =>
      '/api/v1/bikes/${_segment(id)}/damage-reports';

  // Dashboard
  static const String dashboardSummary = '/api/v1/dashboard/summary';
}
