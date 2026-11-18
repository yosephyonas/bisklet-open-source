import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/ride_summary.dart';
import '../services/ride_api_service.dart';
import 'ride_api_providers.dart';
import 'ride_session_controller.dart';

class RideSyncEvent {
  const RideSyncEvent._({this.activeRide, this.endedRide, required this.hasEnded});

  final RideSummary? activeRide;
  final RideSummary? endedRide;
  final bool hasEnded;

  factory RideSyncEvent.active(RideSummary ride) =>
      RideSyncEvent._(activeRide: ride, hasEnded: false);

  factory RideSyncEvent.ended({RideSummary? ride}) =>
      RideSyncEvent._(endedRide: ride, hasEnded: true);
}

final rideStatusStreamProvider = StreamProvider.autoDispose<RideSyncEvent>((ref) {
  final session = ref.watch(rideSessionControllerProvider);
  final rideApi = ref.watch(rideApiServiceProvider);

  if (!session.isRiding) {
    return const Stream<RideSyncEvent>.empty();
  }

  final controller = StreamController<RideSyncEvent>();
  Timer? timer;
  var isClosed = false;

  Future<void> poll() async {
    if (isClosed) return;
    try {
      final active = await rideApi.fetchActiveRide();
      if (active != null) {
        if (!controller.isClosed) {
          controller.add(RideSyncEvent.active(active));
        }
        return;
      }
      final ended = await rideApi.fetchLatestCompletedRide();
      if (!controller.isClosed) {
        controller.add(RideSyncEvent.ended(ride: ended));
        await controller.close();
        isClosed = true;
      }
      timer?.cancel();
    } catch (_) {
      // ignore polling errors to keep stream alive
    }
  }

  timer = Timer.periodic(const Duration(seconds: 5), (_) => poll());

  controller.onListen = poll;
  controller.onCancel = () {
    timer?.cancel();
    if (!controller.isClosed) {
      controller.close();
    }
    isClosed = true;
  };

  ref.onDispose(() {
    timer?.cancel();
    if (!controller.isClosed) {
      controller.close();
    }
    isClosed = true;
  });

  return controller.stream;
});
