import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:geolocator/geolocator.dart' as geo;

import '../../../core/config/mapbox_config.dart';
import '../../../core/network/api_client.dart';

const double _nearbyRadiusMeters = 200;

class BikeLocation {
  BikeLocation({
    required this.id,
    required this.latitude,
    required this.longitude,
    required this.lastUpdated,
    this.distanceMeters,
    this.heading,
    this.batteryLevel,
    this.pricePerMinute,
    this.isReserved = false,
  });

  final String id;
  final double latitude;
  final double longitude;
  final DateTime lastUpdated;
  final double? distanceMeters;
  final double? heading;
  final int? batteryLevel;
  final double? pricePerMinute;
  final bool isReserved;

  BikeLocation copyWith({
    double? latitude,
    double? longitude,
    DateTime? lastUpdated,
    double? distanceMeters,
    double? heading,
    int? batteryLevel,
    double? pricePerMinute,
    bool? isReserved,
  }) {
    return BikeLocation(
      id: id,
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      lastUpdated: lastUpdated ?? this.lastUpdated,
      distanceMeters: distanceMeters ?? this.distanceMeters,
      heading: heading ?? this.heading,
      batteryLevel: batteryLevel ?? this.batteryLevel,
      pricePerMinute: pricePerMinute ?? this.pricePerMinute,
      isReserved: isReserved ?? this.isReserved,
    );
  }

  static BikeLocation? fromPayload(dynamic payload) {
    if (payload == null) {
      return null;
    }

    dynamic normalized = payload;
    if (payload is String && payload.isNotEmpty) {
      try {
        normalized = jsonDecode(payload);
      } catch (_) {
        return null;
      }
    }

    if (normalized is! Map<String, dynamic>) {
      return null;
    }

    final id =
        normalized['id'] ?? normalized['bikeId'] ?? normalized['bike_id'];
    final lat = normalized['lat'] ?? normalized['latitude'];
    final lng = normalized['lng'] ?? normalized['longitude'];
    if (id == null || lat == null || lng == null) {
      return null;
    }

    final heading = (normalized['heading'] ?? normalized['bearing'])
        ?.toDouble();
    final battery = normalized['battery'] ?? normalized['batteryLevel'];
    final reserved = normalized['reserved'] ?? normalized['isReserved'];
    final pricePerMinute =
        _asDouble(normalized['price_per_minute']) ??
        _asDouble(normalized['pricePerMinute']) ??
        _asDouble(normalized['per_minute_rate']);

    return BikeLocation(
      id: '$id',
      latitude: (lat as num).toDouble(),
      longitude: (lng as num).toDouble(),
      heading: heading,
      batteryLevel: battery is num ? battery.toInt() : null, 
      pricePerMinute: pricePerMinute,
      isReserved: reserved is bool ? reserved : false,
      lastUpdated: DateTime.now(),
    );
  }

  static BikeLocation? fromApiJson(Map<String, dynamic> json) {
    final metadata = json['metadata'] as Map<String, dynamic>?;
    final telemetry = metadata?['telemetry'] as Map<String, dynamic>?;
    final station = json['station'] as Map<String, dynamic>?;

    final lat = _asDouble(json['last_latitude']) ??
        _asDouble(json['latitude']) ??
        _asDouble(telemetry?['latitude']) ??
        _asDouble(station?['latitude']) ??
        _asDouble(station?['lat']);
    final lng = _asDouble(json['last_longitude']) ??
        _asDouble(json['longitude']) ??
        _asDouble(telemetry?['longitude']) ??
        _asDouble(station?['longitude']) ??
        _asDouble(station?['lng']);
    if (lat == null || lng == null) {
      return null;
    }

    final id = json['code'] ?? json['id'];
    if (id == null) return null;

    final battery = json['battery_level'];
    final statusText = json['status']?.toString().toLowerCase();
    final reserved = statusText == null ? false : statusText != 'available';
    final pricePerMinute =
        _asDouble(json['price_per_minute']) ??
        _asDouble(telemetry?['price_per_minute']);

    final updatedAtRaw = json['location_updated_at'] ?? json['updated_at'];
    DateTime? updated;
    if (updatedAtRaw is String) {
      updated = DateTime.tryParse(updatedAtRaw);
    }

    return BikeLocation(
      id: '$id',
      latitude: lat,
      longitude: lng,
      distanceMeters: _asDouble(json['distance_m']),
      batteryLevel: battery is num ? battery.toInt() : null,
      pricePerMinute: pricePerMinute,
      isReserved: reserved,
      lastUpdated: updated ?? DateTime.now(),
    );
  }
}

double? _asDouble(dynamic value) {
  if (value == null) return null;
  if (value is num) return value.toDouble();
  if (value is String) {
    return double.tryParse(value);
  }
  return null;
}

class BikeTrackingService {
  BikeTrackingService({
    ApiClient? apiClient,
    Duration pollingInterval = const Duration(seconds: 6),
  })  : _apiClient = apiClient,
        _pollingInterval = pollingInterval;

  final ApiClient? _apiClient;
  final Duration _pollingInterval;
  geo.Position? _userPosition;

  void updateUserPosition(geo.Position? position) {
    _userPosition = position;
  }

  Stream<List<BikeLocation>> bikeLocations() {
    final api = _apiClient;
    if (api != null) {
      return _apiPollingStream(api);
    }
    return _mockStream;
  }

  Stream<List<BikeLocation>> get _mockStream {
    final random = Random();
    final bikes = List.generate(12, (index) => 'bike-${index + 1}');
    final centerLat = MapboxConfig.defaultLatitude;
    final centerLng = MapboxConfig.defaultLongitude;

    final Map<String, BikeLocation> current = {
      for (final id in bikes)
        id: BikeLocation(
        id: id,
        latitude:
            centerLat + (random.nextDouble() - 0.5) * 0.05, // ~5km spread
        longitude: centerLng + (random.nextDouble() - 0.5) * 0.05, //
        lastUpdated: DateTime.now(),
        batteryLevel: 40 + random.nextInt(55),
        heading: random.nextDouble() * 360,
        pricePerMinute: 2.5 + (random.nextDouble() * 1.5),
      ),
    };

    return Stream<List<BikeLocation>>.periodic(const Duration(seconds: 3), (_) {
      for (final entry in current.entries) {
        final latDelta = (random.nextDouble() - 0.5) * 0.002;
        final lngDelta = (random.nextDouble() - 0.5) * 0.002;

        final nextBattery = max(
          5,
          (entry.value.batteryLevel ?? 70) - (random.nextBool() ? 1 : 0),
        );

        current[entry.key] = entry.value.copyWith(
          latitude: entry.value.latitude + latDelta,
          longitude: entry.value.longitude + lngDelta,
          lastUpdated: DateTime.now(),
          heading:
              ((entry.value.heading ?? 0) + random.nextDouble() * 30) % 360,
          batteryLevel: nextBattery,
        );
      }
      return current.values.toList();
    }).asBroadcastStream();
  }

  Stream<List<BikeLocation>> _apiPollingStream(ApiClient api) {
    final controller = StreamController<List<BikeLocation>>.broadcast();
    Timer? timer;
    var fetching = false;
    Future<void> pump() async {
      if (fetching) return;
      fetching = true;
      try {
        final snapshot = await _fetchBikeLocations(api);
        if (!controller.isClosed) {
          controller.add(snapshot);
        }
      } catch (err, st) {
        if (!controller.isClosed) {
          controller.addError(err, st);
        }
      } finally {
        fetching = false;
      }
    }

    controller.onListen = () {
      pump();
      timer = Timer.periodic(_pollingInterval, (_) => pump());
    };

    controller.onCancel = () {
      timer?.cancel();
    };

    return controller.stream;
  }

  Future<List<BikeLocation>> _fetchBikeLocations(ApiClient api) async {
    Map<String, dynamic> response;
    try {
      final pos = _userPosition;
      final path = pos == null
          ? '/api/v1/bikes?per_page=100'
          : '/api/v1/bikes?per_page=100&lat=${pos.latitude}&lng=${pos.longitude}&radius_m=${_nearbyRadiusMeters.toInt()}';
      response = await api.get(path);
    } on ApiException catch (err) {
      if (err.statusCode == 401) {
        return const [];
      }
      rethrow;
    }
    final data = response['data'];
    if (data is! List) {
      return const [];
    }
    final locations = <BikeLocation>[];
    for (final item in data) {
      if (item is Map<String, dynamic>) {
        final loc = BikeLocation.fromApiJson(item);
        if (loc != null) {
          locations.add(loc);
        }
      }
    }
    return locations;
  }
}
