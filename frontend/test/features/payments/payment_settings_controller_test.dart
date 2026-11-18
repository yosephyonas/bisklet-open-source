import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:bisklet/core/app_bootstrap.dart';
import 'package:bisklet/core/providers/app_providers.dart';
import 'package:bisklet/core/storage/app_storage.dart';
import 'package:bisklet/features/payments/application/payment_settings_controller.dart';

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

  test('initial settings come from storage defaults', () {
    final state = container.read(paymentSettingsControllerProvider);

    expect(state.autoTopUpEnabled, isTrue);
    expect(state.billingEmail, AppStorage.defaultBillingEmail);
  });

  test('setAutoTopUpEnabled persists preference', () async {
    final notifier = container.read(paymentSettingsControllerProvider.notifier);

    await notifier.setAutoTopUpEnabled(false);

    final state = container.read(paymentSettingsControllerProvider);
    final storage = container.read(appStorageProvider);
    expect(state.autoTopUpEnabled, isFalse);
    expect(storage.autoTopUpEnabled, isFalse);
  });

  test('updateBillingEmail trims input and stores value', () async {
    final notifier = container.read(paymentSettingsControllerProvider.notifier);

    await notifier.updateBillingEmail(' rider@bisklet.app ');

    final state = container.read(paymentSettingsControllerProvider);
    final storage = container.read(appStorageProvider);
    expect(state.billingEmail, 'rider@bisklet.app');
    expect(storage.billingEmail, 'rider@bisklet.app');
  });

  test('updateBillingEmail clears to default when empty', () async {
    final notifier = container.read(paymentSettingsControllerProvider.notifier);

    await notifier.updateBillingEmail('');

    final state = container.read(paymentSettingsControllerProvider);
    final storage = container.read(appStorageProvider);
    expect(state.billingEmail, AppStorage.defaultBillingEmail);
    expect(storage.billingEmail, AppStorage.defaultBillingEmail);
  });
}
