import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:bisklet/core/app_bootstrap.dart';
import 'package:bisklet/core/providers/app_providers.dart';
import 'package:bisklet/features/home/application/home_navigation_controller.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    await AppBootstrap.init();
  });

  late ProviderContainer container;

  setUp(() async {
    await AppBootstrap.clearForTest();
    container = ProviderContainer();
  });

  tearDown(() {
    container.dispose();
  });

  test('initial tab index comes from storage', () async {
    final storage = container.read(appStorageProvider);
    await storage.setHomeTabIndex(2);

    final currentIndex = container.read(homeNavigationControllerProvider);
    expect(currentIndex, 2);
  });

  test('setIndex updates state and persists value', () async {
    final notifier = container.read(homeNavigationControllerProvider.notifier);

    await notifier.setIndex(3);

    final currentIndex = container.read(homeNavigationControllerProvider);
    final storage = container.read(appStorageProvider);
    expect(currentIndex, 3);
    expect(storage.homeTabIndex, 3);
  });
}
