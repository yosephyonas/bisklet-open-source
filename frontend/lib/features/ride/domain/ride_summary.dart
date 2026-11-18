class RideSummary {
  const RideSummary({
    required this.id,
    required this.status,
    this.startedAt,
    this.endedAt,
    this.durationSeconds,
    this.distanceMeters,
    this.perMinuteRate,
    this.unlockFee,
    this.totalAmount,
    this.estimatedTotalAmount,
    this.currency,
    this.bikeCode,
    this.lockCode,
    this.imei,
    this.minutes,
    this.estimatedMinutes,
    this.priceTotal,
    this.pricingBreakdown,
  });

  final String id;
  final String status;
  final DateTime? startedAt;
  final DateTime? endedAt;
  final int? durationSeconds;
  final double? distanceMeters;
  final double? perMinuteRate;
  final double? unlockFee;
  final double? totalAmount;
  final double? estimatedTotalAmount;
  final String? currency;
  final String? bikeCode;
  final String? lockCode;
  final String? imei;
  final int? minutes;
  final int? estimatedMinutes;
  final double? priceTotal;
  final Map<String, dynamic>? pricingBreakdown;

  factory RideSummary.fromJson(Map<String, dynamic> json) {
    final bike = json['bike'] as Map<String, dynamic>?;
    return RideSummary(
      id: (json['id'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      startedAt: _parseDate(json['started_at']),
      endedAt: _parseDate(json['ended_at']),
      durationSeconds: _toInt(json['duration_seconds']),
      distanceMeters: _toDouble(json['distance_m']),
      perMinuteRate: _toDouble(json['per_minute_rate']),
      unlockFee: _toDouble(json['unlock_fee']),
      totalAmount: _toDouble(json['total_amount']),
      estimatedTotalAmount: _toDouble(json['estimated_total_amount']),
      currency: json['currency']?.toString(),
      bikeCode: bike?['code']?.toString(),
      lockCode: json['lock_code']?.toString(),
      imei: json['imei']?.toString(),
      minutes: _toInt(json['minutes']),
      estimatedMinutes: _toInt(json['estimated_minutes']),
      priceTotal: _toDouble(json['price_total']),
      pricingBreakdown: json['pricing_breakdown'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(
              json['pricing_breakdown'] as Map<String, dynamic>,
            )
          : null,
    );
  }

  String get bikeLabel {
    final label = bikeCode ?? lockCode ?? imei;
    if (label == null || label.trim().isEmpty) {
      return 'BISK-001';
    }
    return label;
  }

  double get totalCost => totalAmount ?? priceTotal ?? 0.0;
  double get estimatedCost => estimatedTotalAmount ?? totalCost;

  double get distanceKm => (distanceMeters ?? 0) / 1000;

  double get durationMinutes {
    if (minutes != null && minutes! > 0) {
      return minutes!.toDouble();
    }
    if (estimatedMinutes != null && estimatedMinutes! > 0) {
      return estimatedMinutes!.toDouble();
    }
    if (durationSeconds != null && durationSeconds! > 0) {
      return durationSeconds! / 60.0;
    }
    return (minutes ?? 0).toDouble();
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
