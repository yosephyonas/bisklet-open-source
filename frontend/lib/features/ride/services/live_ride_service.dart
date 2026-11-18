import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:web_socket_channel/web_socket_channel.dart';

import '../../../core/config/environment.dart';
import '../domain/ride_metrics.dart';

class LiveRideService {
  LiveRideService({WebSocketChannel Function()? channelFactory})
    : _channelFactory = channelFactory;

  final WebSocketChannel Function()? _channelFactory;

  Stream<RideMetrics> rideMetricsStream() {
    if (_isMockUrl) {
      return _mockStream;
    }

    return Stream.multi((controller) {
      WebSocketChannel? channel;
      try {
        channel =
            (_channelFactory ??
            () => WebSocketChannel.connect(
              Uri.parse(AppEnvironment.rideUpdatesWsUrl),
            ))();
      } catch (err) {
        controller.addStream(_mockStream);
        controller.close();
        return;
      }

      final sub = channel.stream.listen(
        (event) {
          try {
            final payload = event is String ? jsonDecode(event) : event;
            if (payload is Map<String, dynamic>) {
              controller.add(RideMetrics.fromJson(payload));
            }
          } catch (_) {
            // ignore malformed messages
          }
        },
        onError: controller.addError,
        onDone: controller.close,
      );

      controller.onCancel = () {
        sub.cancel();
        channel?.sink.close();
      };
    }).handleError((_) {}).cast<RideMetrics>();
  }

  bool get _isMockUrl =>
      AppEnvironment.rideUpdatesWsUrl.isEmpty ||
      AppEnvironment.rideUpdatesWsUrl.contains('example.com');

  Stream<RideMetrics> get _mockStream {
    final random = Random();
    return Stream<RideMetrics>.periodic(const Duration(seconds: 3), (tick) {
      final base = RideMetrics.initial();
      final distance = (tick + 1) * 0.6 + random.nextDouble() * 0.3;
      final duration = (tick + 1) * 2.5;
      return base.copyWith(
        distanceKm: distance,
        durationMinutes: duration,
        speedKmh: distance / (duration / 60).clamp(1, double.infinity),
        batteryLevel: max(10, 100 - tick * 4),
        co2SavedKg: distance * 0.12,
        lastUpdated: DateTime.now(),
        isLive: true,
      );
    }).asBroadcastStream();
  }
}
