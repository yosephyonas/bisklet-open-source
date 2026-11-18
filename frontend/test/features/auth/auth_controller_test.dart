import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:bisklet/core/app_bootstrap.dart';
import 'package:bisklet/core/providers/app_providers.dart';
import 'package:bisklet/features/auth/application/auth_controller.dart';

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

  test('initial state loads values from storage', () async {
    final storage = container.read(appStorageProvider);
    await storage.setFydaNumber('123456');
    await storage.setFullName('Abebe Bikila');
    await storage.setHasSeenOnboarding(true);

    final state = container.read(authControllerProvider);
    expect(state.fydaNumber, '123456');
    expect(state.fullName, 'Abebe Bikila');
    expect(state.hasSeenOnboarding, isTrue);
  });

  test('createAccount persists credentials and updates state', () async {
    final notifier = container.read(authControllerProvider.notifier);

    await notifier.createAccount(
      fydaNumber: '654321',
      fullName: 'Derartu Tulu',
    );

    final state = container.read(authControllerProvider);
    expect(state.fydaNumber, '654321');
    expect(state.fullName, 'Derartu Tulu');
    expect(state.hasSeenOnboarding, isTrue);

    final storage = container.read(appStorageProvider);
    expect(storage.fydaNumber, '654321');
    expect(storage.fullName, 'Derartu Tulu');
    expect(storage.hasSeenOnboarding, isTrue);
  });

  test('signOut clears persisted profile data', () async {
    final notifier = container.read(authControllerProvider.notifier);

    await notifier.createAccount(
      fydaNumber: '111222',
      fullName: 'Almaz Ayana',
    );
    await notifier.signOut();

    final state = container.read(authControllerProvider);
    expect(state.fydaNumber, isNull);
    expect(state.fullName, isNull);
    expect(state.hasSeenOnboarding, isTrue);

    final storage = container.read(appStorageProvider);
    expect(storage.fydaNumber, isNull);
    expect(storage.fullName, isNull);
    expect(storage.hasSeenOnboarding, isTrue);
  });
}
