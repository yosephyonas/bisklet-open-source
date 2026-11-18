import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:geolocator/geolocator.dart' as geo;

import '../../../core/config/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../domain/ride_summary.dart';
import 'ride_api_service.dart';

class RideLocationService with WidgetsBindingObserver {
  RideLocationService({
    required ApiClient apiClient,
    required RideApiService rideApiService,
  })  : _apiClient = apiClient,
        _rideApiService = rideApiService;

  final ApiClient _apiClient;
  final RideApiService _rideApiService;

  StreamSubscription<geo.Position>? _subscription;
  DateTime? _lastSentAt;
  String? _activeRideId;
  bool _isRunning = false;
  bool _observerRegistered = false;
  bool _isForeground = true;

  bool get isRunning => _isRunning;

  Future<void> start({String? rideId}) async {
    if (_isRunning) return;

    final permission = await _ensurePermission();
    if (!permission) return;

    _activeRideId = rideId;
    _isRunning = true;
    _registerLifecycleObserver();

    if (_isForeground) {
      _startStream();
    }
  }

  Future<void> stop() async {
    _isRunning = false;
    _activeRideId = null;
    _lastSentAt = null;
    await _subscription?.cancel();
    _subscription = null;
    _unregisterLifecycleObserver();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _isForeground = state == AppLifecycleState.resumed;
    if (!_isRunning) return;
    if (_isForeground) {
      _startStream();
    } else {
      _stopStream();
    }
  }

  void _startStream() {
    if (_subscription != null) return;
    final settings = geo.LocationSettings(
      accuracy: geo.LocationAccuracy.best,
      distanceFilter: 10,
    );
    _subscription = geo.Geolocator.getPositionStream(locationSettings: settings)
        .listen(_handlePosition, onError: (_) {
      // ignore stream errors to keep tracking alive
    });
  }

  void _stopStream() {
    _subscription?.cancel();
    _subscription = null;
  }

  Future<void> _handlePosition(geo.Position position) async {
    if (!_isRunning) return;

    final now = DateTime.now();
    if (_lastSentAt != null && now.difference(_lastSentAt!).inSeconds < 3) {
      return;
    }

    final rideId = await _resolveRideId();
    if (rideId == null) return;

    _lastSentAt = now;

    try {
      await _apiClient.post(ApiEndpoints.rideLocation(rideId), {
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy_m': position.accuracy,
        'speed_mps': position.speed,
        'bearing': position.heading,
        'captured_at': position.timestamp?.toIso8601String(),
      });
    } catch (_) {
      // ignore network errors; next tick will try again
    }
  }

  Future<String?> _resolveRideId() async {
    if (_activeRideId != null && _activeRideId!.isNotEmpty) {
      return _activeRideId;
    }
    RideSummary? active;
    try {
      active = await _rideApiService.fetchActiveRide();
    } catch (_) {
      return null;
    }
    _activeRideId = active?.id;
    return _activeRideId;
  }

  Future<bool> _ensurePermission() async {
    if (!await geo.Geolocator.isLocationServiceEnabled()) {
      return false;
    }
    var permission = await geo.Geolocator.checkPermission();
    if (permission == geo.LocationPermission.denied) {
      permission = await geo.Geolocator.requestPermission();
    }
    if (permission == geo.LocationPermission.denied ||
        permission == geo.LocationPermission.deniedForever) {
      return false;
    }
    return true;
  }

  void _registerLifecycleObserver() {
    if (_observerRegistered) return;
    WidgetsBinding.instance.addObserver(this);
    _observerRegistered = true;
  }

  void _unregisterLifecycleObserver() {
    if (!_observerRegistered) return;
    WidgetsBinding.instance.removeObserver(this);
    _observerRegistered = false;
  }
}
