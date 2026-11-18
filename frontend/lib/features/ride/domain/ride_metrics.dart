class RideMetrics {
  const RideMetrics({
    required this.bikeId,
    required this.distanceKm,
    required this.durationMinutes,
    required this.speedKmh,
    required this.batteryLevel,
    required this.co2SavedKg,
    required this.lastUpdated,
    required this.isLive,
  });

  final String bikeId;
  final double distanceKm;
  final double durationMinutes;
  final double speedKmh;
  final int batteryLevel;
  final double co2SavedKg;
  final DateTime lastUpdated;
  final bool isLive;

  factory RideMetrics.initial() {
    return RideMetrics(
      bikeId: 'BISK-001',
      distanceKm: 0,
      durationMinutes: 0,
      speedKmh: 0,
      batteryLevel: 100,
      co2SavedKg: 0,
      lastUpdated: DateTime.now(),
      isLive: false,
    );
  }

  RideMetrics copyWith({
    String? bikeId,
    double? distanceKm,
    double? durationMinutes,
    double? speedKmh,
    int? batteryLevel,
    double? co2SavedKg,
    DateTime? lastUpdated,
    bool? isLive,
  }) {
    return RideMetrics(
      bikeId: bikeId ?? this.bikeId,
      distanceKm: distanceKm ?? this.distanceKm,
      durationMinutes: durationMinutes ?? this.durationMinutes,
      speedKmh: speedKmh ?? this.speedKmh,
      batteryLevel: batteryLevel ?? this.batteryLevel,
      co2SavedKg: co2SavedKg ?? this.co2SavedKg,
      lastUpdated: lastUpdated ?? this.lastUpdated,
      isLive: isLive ?? this.isLive,
    );
  }

  factory RideMetrics.fromJson(Map<String, dynamic> json) {
    return RideMetrics(
      bikeId: json['bikeId'] as String? ?? 'BISK-001',
      distanceKm: (json['distanceKm'] as num?)?.toDouble() ?? 0,
      durationMinutes: (json['durationMinutes'] as num?)?.toDouble() ?? 0,
      speedKmh: (json['speedKmh'] as num?)?.toDouble() ?? 0,
      batteryLevel: (json['batteryLevel'] as num?)?.toInt() ?? 0,
      co2SavedKg: (json['co2SavedKg'] as num?)?.toDouble() ?? 0,
      lastUpdated:
          DateTime.tryParse(json['lastUpdated'] as String? ?? '') ??
          DateTime.now(),
      isLive: json['isLive'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() => {
    'bikeId': bikeId,
    'distanceKm': distanceKm,
    'durationMinutes': durationMinutes,
    'speedKmh': speedKmh,
    'batteryLevel': batteryLevel,
    'co2SavedKg': co2SavedKg,
    'lastUpdated': lastUpdated.toIso8601String(),
    'isLive': isLive,
  };
}
