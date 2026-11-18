class RideReceipt {
  const RideReceipt({
    required this.id,
    required this.status,
    this.startedAt,
    this.endedAt,
    this.durationSeconds,
    this.distanceMeters,
    this.totalAmount,
    this.currency,
    this.unlockFee,
    this.perMinuteRate,
    this.minutes,
    this.bikeCode,
    this.startStationName,
    this.endStationName,
    this.pricingBreakdown,
  });

  final String id;
  final String status;
  final DateTime? startedAt;
  final DateTime? endedAt;
  final int? durationSeconds;
  final double? distanceMeters;
  final double? totalAmount;
  final String? currency;
  final double? unlockFee;
  final double? perMinuteRate;
  final int? minutes;
  final String? bikeCode;
  final String? startStationName;
  final String? endStationName;
  final Map<String, dynamic>? pricingBreakdown;

  factory RideReceipt.fromJson(Map<String, dynamic> json) {
    final bike = json['bike'] as Map<String, dynamic>?;
    final startStation = json['start_station'] as Map<String, dynamic>?;
    final endStation = json['end_station'] as Map<String, dynamic>?;
    return RideReceipt(
      id: (json['id'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      startedAt: _parseDate(json['started_at']),
      endedAt: _parseDate(json['ended_at']),
      durationSeconds: _toInt(json['duration_seconds']),
      distanceMeters: _toDouble(json['distance_m']),
      totalAmount: _toDouble(json['total_amount']),
      currency: json['currency']?.toString(),
      unlockFee: _toDouble(json['unlock_fee']),
      perMinuteRate: _toDouble(json['per_minute_rate']),
      minutes: _toInt(json['minutes']),
      bikeCode: bike?['code']?.toString(),
      startStationName: startStation?['name']?.toString(),
      endStationName: endStation?['name']?.toString(),
      pricingBreakdown: json['pricing_breakdown'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(
              json['pricing_breakdown'] as Map<String, dynamic>,
            )
          : null,
    );
  }

  double get distanceKm => (distanceMeters ?? 0) / 1000;
  double get durationMinutes {
    if (minutes != null && minutes! > 0) return minutes!.toDouble();
    if (durationSeconds != null && durationSeconds! > 0) {
      return durationSeconds! / 60.0;
    }
    return 0.0;
  }
}

DateTime? _parseDate(dynamic raw) {
  if (raw is String && raw.isNotEmpty) {
    return DateTime.tryParse(raw);
  }
  return null;
}

double? _toDouble(dynamic value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value);
  return null;
}

int? _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}
