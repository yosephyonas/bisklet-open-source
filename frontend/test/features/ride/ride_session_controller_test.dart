import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:bisklet/core/app_bootstrap.dart';
import 'package:bisklet/core/providers/app_providers.dart';
import 'package:bisklet/features/ride/application/ride_session_controller.dart';

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

  test('reserveBike stores reservation details', () async {
    final notifier = container.read(rideSessionControllerProvider.notifier);

    await notifier.reserveBike(
      '  BISK-42  ',
      holdDuration: const Duration(minutes: 5),
    );

    final state = container.read(rideSessionControllerProvider);
    final storage = container.read(appStorageProvider);

    expect(state.hasReservation, isTrue);
    expect(state.bikeId, 'BISK-42');
    expect(state.status, RideSessionStatus.reserved);
    expect(storage.rideSessionStatus, RideSessionStatus.reserved.storageValue);
    expect(storage.rideSessionBikeId, 'BISK-42');
    expect(storage.rideReservationExpiry, isNotNull);
  });

  test('startRide transitions reservation to in-ride state', () async {
    final notifier = container.read(rideSessionControllerProvider.notifier);

    await notifier.reserveBike('BISK-007');
    await notifier.startRide();

    final state = container.read(rideSessionControllerProvider);
    final storage = container.read(appStorageProvider);

    expect(state.isRiding, isTrue);
    expect(state.reservationExpiry, isNull);
    expect(storage.rideSessionStatus, RideSessionStatus.inRide.storageValue);
    expect(storage.rideReservationExpiry, isNull);
    expect(storage.rideStartedAt, isNotNull);
  });

  test('endRide clears the persisted session', () async {
    final notifier = container.read(rideSessionControllerProvider.notifier);

    await notifier.reserveBike('BISK-021');
    await notifier.endRide();

    final state = container.read(rideSessionControllerProvider);
    final storage = container.read(appStorageProvider);

    expect(state.isIdle, isTrue);
    expect(state.bikeId, isNull);
    expect(storage.rideSessionStatus, 'idle');
    expect(storage.rideSessionBikeId, isNull);
  });
}
