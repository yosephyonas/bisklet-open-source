import '../../../core/config/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../domain/notification_models.dart';

class NotificationService {
  NotificationService({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<List<AppNotification>> fetchNotifications({int perPage = 20}) async {
    final response = await _apiClient.get(
      '${ApiEndpoints.notifications}?per_page=$perPage',
    );
    final data = response['data'];
    if (data is List) {
      return data
          .whereType<Map<String, dynamic>>()
          .map(AppNotification.fromJson)
          .toList();
    }
    return const [];
  }

  Future<AppNotification?> markRead(String id) async {
    if (id.trim().isEmpty) return null;
    final response = await _apiClient.post(
      ApiEndpoints.notificationRead(id),
      const {},
    );
    final data = response['data'];
    if (data is Map<String, dynamic>) {
      return AppNotification.fromJson(data);
    }
    return null;
  }

  Future<void> markAllRead() async {
    await _apiClient.post(ApiEndpoints.notificationsReadAll, const {});
  }
}
