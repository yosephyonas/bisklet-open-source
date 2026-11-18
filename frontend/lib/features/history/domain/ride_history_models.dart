class RideHistoryRide {
  const RideHistoryRide({
    required this.id,
    required this.status,
    required this.startedAt,
    required this.endedAt,
    required this.createdAt,
    required this.durationSeconds,
    required this.distanceMeters,
    required this.totalAmount,
    required this.currency,
    required this.startStationName,
    required this.endStationName,
    required this.bikeCode,
    required this.minutes,
  });

  final String id;
  final String status;
  final DateTime? startedAt;
  final DateTime? endedAt;
  final DateTime? createdAt;
  final int? durationSeconds;
  final double? distanceMeters;
  final double? totalAmount;
  final String? currency;
  final String? startStationName;
  final String? endStationName;
  final String? bikeCode;
  final int? minutes;

  factory RideHistoryRide.fromJson(Map<String, dynamic> json) {
    final startStation = json['start_station'] as Map<String, dynamic>?;
    final endStation = json['end_station'] as Map<String, dynamic>?;
    final bike = json['bike'] as Map<String, dynamic>?;
    return RideHistoryRide(
      id: (json['id'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      startedAt: _parseDate(json['started_at']),
      endedAt: _parseDate(json['ended_at']),
      createdAt: _parseDate(json['created_at']),
      durationSeconds: (json['duration_seconds'] as num?)?.toInt(),
      distanceMeters: (json['distance_m'] as num?)?.toDouble(),
      totalAmount: (json['total_amount'] as num?)?.toDouble(),
      currency: json['currency'] as String?,
      minutes: (json['minutes'] as num?)?.toInt(),
      startStationName: startStation?['name'] as String?,
      endStationName: endStation?['name'] as String?,
      bikeCode: bike?['code'] as String?,
    );
  }

  DateTime? get primaryDate => endedAt ?? startedAt ?? createdAt;
}

DateTime? _parseDate(dynamic raw) {
  if (raw is String && raw.isNotEmpty) {
    return DateTime.tryParse(raw);
  }
  return null;
}
