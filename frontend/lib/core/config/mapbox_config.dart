class MapboxConfig {
  const MapboxConfig._();

  static String accessToken = const String.fromEnvironment(
    'MAPBOX_ACCESS_TOKEN',
    defaultValue: '',
  );

  static String downloadsToken = const String.fromEnvironment(
    'MAPBOX_DOWNLOADS_TOKEN',
    defaultValue: '',
  );

  static String navigationProfile = const String.fromEnvironment(
    'MAPBOX_NAVIGATION_PROFILE',
    defaultValue: 'cycling',
  );

  static bool get hasValidAccessToken =>
      accessToken.trim().isNotEmpty && accessToken.trim().startsWith('pk.');

  static bool get hasValidDownloadsToken =>
      downloadsToken.trim().isNotEmpty &&
      downloadsToken.trim().startsWith('sk.');

  static void applyRuntime({
    String? accessToken,
    String? downloadsToken,
    String? navigationProfile,
  }) {
    void updateIfValue(String? value, void Function(String) setter) {
      if (value == null) {
        return;
      }
      final trimmed = value.trim();
      if (trimmed.isEmpty) {
        return;
      }
      setter(trimmed);
    }

    updateIfValue(accessToken, (value) => MapboxConfig.accessToken = value);
    updateIfValue(
      downloadsToken,
      (value) => MapboxConfig.downloadsToken = value,
    );
    updateIfValue(
      navigationProfile,
      (value) => MapboxConfig.navigationProfile = value,
    );
  }

  static String maskToken(String token) {
    final trimmed = token.trim();
    if (trimmed.isEmpty) {
      return 'none';
    }
    if (trimmed.length <= 12) {
      return '${trimmed.substring(0, 3)}...';
    }
    final start = trimmed.substring(0, 6);
    final end = trimmed.substring(trimmed.length - 6);
    return '$start...$end';
  }

  static const double defaultLatitude = 8.9806;
  static const double defaultLongitude = 38.7578;
}
