import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../../../core/storage/app_storage.dart';

final paymentSettingsControllerProvider = StateNotifierProvider<
    PaymentSettingsController, PaymentSettingsState>((ref) {
  final storage = ref.watch(appStorageProvider);
  return PaymentSettingsController(storage);
});

class PaymentSettingsState {
  const PaymentSettingsState({
    required this.autoTopUpEnabled,
    required this.billingEmail,
  });

  final bool autoTopUpEnabled;
  final String billingEmail;

  PaymentSettingsState copyWith({
    bool? autoTopUpEnabled,
    String? billingEmail,
  }) {
    return PaymentSettingsState(
      autoTopUpEnabled: autoTopUpEnabled ?? this.autoTopUpEnabled,
      billingEmail: billingEmail ?? this.billingEmail,
    );
  }

  factory PaymentSettingsState.fromStorage(AppStorage storage) {
    return PaymentSettingsState(
      autoTopUpEnabled: storage.autoTopUpEnabled,
      billingEmail: storage.billingEmail,
    );
  }
}

class PaymentSettingsController extends StateNotifier<PaymentSettingsState> {
  PaymentSettingsController(this._storage)
      : super(PaymentSettingsState.fromStorage(_storage));

  final AppStorage _storage;

  Future<void> setAutoTopUpEnabled(bool value) async {
    if (state.autoTopUpEnabled == value) {
      return;
    }
    state = state.copyWith(autoTopUpEnabled: value);
    await _storage.setAutoTopUpEnabled(value);
  }

  Future<void> updateBillingEmail(String email) async {
    final trimmed = email.trim();
    if (trimmed.isEmpty) {
      state = state.copyWith(billingEmail: AppStorage.defaultBillingEmail);
      await _storage.clearBillingEmail();
      return;
    }

    if (state.billingEmail == trimmed) {
      return;
    }

    state = state.copyWith(billingEmail: trimmed);
    await _storage.setBillingEmail(trimmed);
  }
}
