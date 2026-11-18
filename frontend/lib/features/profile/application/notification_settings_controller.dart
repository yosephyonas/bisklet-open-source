import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../../../core/storage/app_storage.dart';

final notificationSettingsControllerProvider = StateNotifierProvider<
    NotificationSettingsController, NotificationSettingsState>((ref) {
  final storage = ref.watch(appStorageProvider);
  return NotificationSettingsController(storage);
});

class NotificationSettingsState {
  const NotificationSettingsState({
    required this.pushRideUpdates,
    required this.pushPromotions,
    required this.emailReceipts,
  });

  final bool pushRideUpdates;
  final bool pushPromotions;
  final bool emailReceipts;

  NotificationSettingsState copyWith({
    bool? pushRideUpdates,
    bool? pushPromotions,
    bool? emailReceipts,
  }) {
    return NotificationSettingsState(
      pushRideUpdates: pushRideUpdates ?? this.pushRideUpdates,
      pushPromotions: pushPromotions ?? this.pushPromotions,
      emailReceipts: emailReceipts ?? this.emailReceipts,
    );
  }

  factory NotificationSettingsState.fromStorage(AppStorage storage) {
    return NotificationSettingsState(
      pushRideUpdates: storage.pushRideUpdatesEnabled,
      pushPromotions: storage.pushPromotionsEnabled,
      emailReceipts: storage.emailReceiptsEnabled,
    );
  }
}

class NotificationSettingsController
    extends StateNotifier<NotificationSettingsState> {
  NotificationSettingsController(this._storage)
      : super(NotificationSettingsState.fromStorage(_storage));

  final AppStorage _storage;

  Future<void> setPushRideUpdates(bool value) async {
    if (state.pushRideUpdates == value) return;
    state = state.copyWith(pushRideUpdates: value);
    await _storage.setPushRideUpdatesEnabled(value);
  }

  Future<void> setPushPromotions(bool value) async {
    if (state.pushPromotions == value) return;
    state = state.copyWith(pushPromotions: value);
    await _storage.setPushPromotionsEnabled(value);
  }

  Future<void> setEmailReceipts(bool value) async {
    if (state.emailReceipts == value) return;
    state = state.copyWith(emailReceipts: value);
    await _storage.setEmailReceiptsEnabled(value);
  }
}
