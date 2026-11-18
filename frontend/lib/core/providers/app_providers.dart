import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app_bootstrap.dart';
import '../logging/app_logger.dart';
import '../storage/app_storage.dart';

final appLoggerProvider = Provider<AppLogger>((ref) {
  return const DebugAppLogger();
});

final appStorageProvider = Provider<AppStorage>((ref) {
  final box = AppBootstrap.appBox;
  final logger = ref.watch(appLoggerProvider);
  return AppStorage(box, logger: logger);
});

final initialRouteProvider = Provider<String>((ref) {
  final storage = ref.watch(appStorageProvider);
  if (!storage.hasSeenOnboarding) {
    return '/';
  }
  return storage.authToken == null ? '/signin' : '/home';
});
