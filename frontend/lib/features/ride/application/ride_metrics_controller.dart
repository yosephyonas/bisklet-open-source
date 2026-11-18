import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/ride_metrics.dart';
import '../services/live_ride_service.dart';
import 'ride_session_controller.dart';

final liveRideServiceProvider = Provider<LiveRideService>((ref) {
  return LiveRideService();
});

final rideMetricsProvider =
    StateNotifierProvider<RideMetricsController, RideMetrics>((ref) {
      final service = ref.watch(liveRideServiceProvider);
      final controller = RideMetricsController(service);
      ref.onDispose(controller.dispose);
      return controller;
    });

final rideClockProvider = StreamProvider.autoDispose<DateTime>((ref) {
  final session = ref.watch(rideSessionControllerProvider);
  if (!session.isRiding) {
    return const Stream<DateTime>.empty();
  }
  return Stream<DateTime>.periodic(
    const Duration(seconds: 1),
    (_) => DateTime.now(),
  );
});

class RideMetricsController extends StateNotifier<RideMetrics> {
  RideMetricsController(this._service) : super(RideMetrics.initial()) {
    _subscription = _service.rideMetricsStream().listen(
      (event) {
        state = event;
      },
      onError: (_) {
        // keep previous state on errors
      },
    );
  }

  final LiveRideService _service;
  StreamSubscription<RideMetrics>? _subscription;

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }
}
