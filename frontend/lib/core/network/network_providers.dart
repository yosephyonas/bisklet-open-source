// lib/core/providers/network_providers.dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:bisklet/core/network/api_client.dart';
import 'package:bisklet/core/config/app_config.dart'; // dotenv wrapper
import 'package:bisklet/core/providers/app_providers.dart'
    show appStorageProvider;

/// Provide the compiled API destination from AppConfig.
final apiBaseUrlProvider = Provider<String>((ref) {
  return AppConfig.apiBaseUrl;
});

/// ApiClient provider — constructed from base URL + current stored auth token.
final apiClientProvider = Provider<ApiClient>((ref) {
  final base = ref.watch(apiBaseUrlProvider);
  final storage = ref.watch(appStorageProvider);

  // Resolve the token for every request so a token saved or cleared after this
  // provider is created is reflected immediately without restarting the app.
  return ApiClient(baseUrl: base, authTokenProvider: () => storage.authToken);
});
