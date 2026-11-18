import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/network_providers.dart';
import '../domain/notification_models.dart';
import '../services/notification_service.dart';

final notificationServiceProvider = Provider<NotificationService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return NotificationService(apiClient: apiClient);
});

final notificationsProvider = FutureProvider.autoDispose<List<AppNotification>>((ref) async {
  final service = ref.watch(notificationServiceProvider);
  return service.fetchNotifications();
});
