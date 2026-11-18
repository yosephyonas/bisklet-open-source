class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.createdAt,
    this.readAt,
    this.rideId,
    this.imei,
    this.data,
  });

  final String id;
  final String type;
  final String title;
  final String body;
  final DateTime? createdAt;
  final DateTime? readAt;
  final String? rideId;
  final String? imei;
  final Map<String, dynamic>? data;

  bool get isRead => readAt != null;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: (json['id'] ?? '').toString(),
      type: (json['type'] ?? '').toString(),
      title: (json['title'] ?? '').toString(),
      body: (json['body'] ?? '').toString(),
      createdAt: _parseDate(json['created_at']),
      readAt: _parseDate(json['read_at']),
      rideId: json['ride_id']?.toString(),
      imei: json['imei']?.toString(),
      data: json['data'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['data'] as Map<String, dynamic>)
          : null,
    );
  }
}

DateTime? _parseDate(dynamic raw) {
  if (raw is String && raw.isNotEmpty) {
    return DateTime.tryParse(raw);
  }
  return null;
}
