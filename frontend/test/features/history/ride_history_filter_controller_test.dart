import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:bisklet/core/app_bootstrap.dart';
import 'package:bisklet/core/providers/app_providers.dart';
import 'package:bisklet/features/history/application/ride_history_filter_controller.dart';

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

  test('initial history filter reads persisted preference', () async {
    final storage = container.read(appStorageProvider);
    await storage.setRideHistoryFilter(1);

    final filter = container.read(rideHistoryFilterProvider);
    expect(filter, 1);
  });

  test('setFilter stores selection to Hive', () async {
    final notifier = container.read(rideHistoryFilterProvider.notifier);

    await notifier.setFilter(2);

    final filter = container.read(rideHistoryFilterProvider);
    final storage = container.read(appStorageProvider);
    expect(filter, 2);
    expect(storage.rideHistoryFilter, 2);
  });
}
