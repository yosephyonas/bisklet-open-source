import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/i18n/app_i18n.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/utils/haptics.dart';
import '../../../core/widgets/page_header.dart';
import '../../history/presentation/ride_receipt_page.dart';
import '../application/notification_providers.dart';
import '../application/notification_settings_controller.dart';
import '../domain/notification_models.dart';

class NotificationsPage extends ConsumerWidget {
  const NotificationsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settings = ref.watch(notificationSettingsControllerProvider);
    final settingsNotifier =
        ref.read(notificationSettingsControllerProvider.notifier);
    final notificationsAsync = ref.watch(notificationsProvider);
    final notificationService = ref.read(notificationServiceProvider);
    final storage = ref.watch(appStorageProvider);
    final theme = Theme.of(context);
    final l10n = context.l10n;
    final permissionGranted = storage.isNotificationPermissionGranted;
    final permissionLabel =
        permissionGranted
            ? l10n.tr('notifications.enabled')
            : l10n.tr('notifications.disabled_system');

    return Scaffold(
      body: ListView(
        padding: const EdgeInsets.fromLTRB(0, 8, 0, 32),
        children: [
          PageHeader(
            title: l10n.tr('notifications.title'),
            icon: Icons.notifications,
          ),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      l10n.tr('notifications.recent'),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const Spacer(),
                    TextButton(
                      onPressed: () async {
                        try {
                          await notificationService.markAllRead();
                          ref.invalidate(notificationsProvider);
                        } catch (_) {
                          if (!context.mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(
                                l10n.tr('notifications.server_unreachable'),
                              ),
                            ),
                          );
                        }
                      },
                      child: Text(l10n.tr('notifications.mark_all_read')),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                notificationsAsync.when(
                  data: (items) {
                    if (items.isEmpty) {
                      return Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: theme.colorScheme.surface,
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(
                            color: theme.colorScheme.outlineVariant
                                .withValues(alpha: 0.4),
                          ),
                        ),
                        child: Text(
                          l10n.tr('notifications.none'),
                          style: theme.textTheme.bodyMedium,
                        ),
                      );
                    }
                    return Column(
                      children: items
                          .map(
                            (notification) => _NotificationCard(
                              notification: notification,
                              onTap: () async {
                                await notificationService
                                    .markRead(notification.id);
                                ref.invalidate(notificationsProvider);
                                final rideId = notification.rideId ??
                                    notification.data?['ride_id']?.toString();
                                if (rideId != null &&
                                    rideId.trim().isNotEmpty &&
                                    notification.type == 'ride_ended') {
                                  if (!context.mounted) return;
                                  await Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          RideReceiptPage(rideId: rideId),
                                    ),
                                  );
                                }
                              },
                            ),
                          )
                          .toList(),
                    );
                  },
                  error: (error, _) => Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.surface,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(
                        color: theme.colorScheme.outlineVariant
                            .withValues(alpha: 0.4),
                      ),
                    ),
                    child: Text(
                      l10n.tr('notifications.load_error'),
                      style: theme.textTheme.bodyMedium,
                    ),
                  ),
                  loading: () => const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Center(child: CircularProgressIndicator()),
                  ),
                ),
                const SizedBox(height: 24),
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.surface,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: theme.colorScheme.primary.withValues(alpha: 0.1),
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        permissionGranted
                            ? Icons.notifications_active
                            : Icons.notifications_off_outlined,
                        color: permissionGranted
                            ? theme.colorScheme.primary
                            : theme.colorScheme.onSurfaceVariant,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          '${l10n.tr('notifications.push_status')}: $permissionLabel',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                Text(
                  l10n.tr('notifications.push'),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                SwitchListTile.adaptive(
                  value: settings.pushRideUpdates,
                  onChanged: permissionGranted
                      ? (value) {
                          Haptics.selection();
                          settingsNotifier.setPushRideUpdates(value);
                        }
                      : null,
                  title: Text(l10n.tr('notifications.ride_updates')),
                  subtitle: Text(l10n.tr('notifications.ride_updates_desc')),
                ),
                SwitchListTile.adaptive(
                  value: settings.pushPromotions,
                  onChanged: permissionGranted
                      ? (value) {
                          Haptics.selection();
                          settingsNotifier.setPushPromotions(value);
                        }
                      : null,
                  title: Text(l10n.tr('notifications.promotions')),
                  subtitle: Text(l10n.tr('notifications.promotions_desc')),
                ),
                const SizedBox(height: 16),
                Text(
                  l10n.tr('notifications.email'),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                SwitchListTile.adaptive(
                  value: settings.emailReceipts,
                  onChanged: (value) {
                    Haptics.selection();
                    settingsNotifier.setEmailReceipts(value);
                  },
                  title: Text(l10n.tr('notifications.ride_receipts')),
                  subtitle: Text(l10n.tr('notifications.ride_receipts_desc')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.onTap,
  });

  final AppNotification notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final isUnread = !notification.isRead;
    final timeLabel = _formatNotificationTime(context, notification.createdAt);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isUnread
              ? colorScheme.primary.withValues(alpha: 0.4)
              : colorScheme.outlineVariant.withValues(alpha: 0.4),
        ),
      ),
      child: ListTile(
        onTap: onTap,
        title: Text(
          notification.title,
          style: theme.textTheme.bodyLarge?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(notification.body),
              const SizedBox(height: 6),
              Text(
                timeLabel,
                style: theme.textTheme.bodySmall?.copyWith(
                  color:
                      theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
                ),
              ),
            ],
          ),
        ),
        trailing: isUnread
            ? Container(
                height: 10,
                width: 10,
                decoration: BoxDecoration(
                  color: colorScheme.primary,
                  shape: BoxShape.circle,
                ),
              )
            : null,
      ),
    );
  }
}

String _formatNotificationTime(BuildContext context, DateTime? date) {
  final l10n = context.l10n;
  if (date == null) return l10n.tr('notifications.time_unknown');
  final now = DateTime.now();
  final diff = now.difference(date);
  if (diff.inMinutes < 1) return l10n.tr('notifications.time_just_now');
  if (diff.inMinutes < 60) return '${diff.inMinutes} ${l10n.tr('notifications.min_ago')}';
  if (diff.inHours < 24) return '${diff.inHours} ${l10n.tr('notifications.hr_ago')}';
  return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
}
