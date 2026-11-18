class AppConfig {
  // Supply your own development API URL using --dart-define.
  // Never point a public example build at the Bisklet production backend.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );

  static const String env = String.fromEnvironment(
    'APP_ENV',
    defaultValue: 'development',
  );
}
