import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../../../core/storage/app_storage.dart';
import '../domain/payment_method.dart';

final paymentMethodsControllerProvider =
    StateNotifierProvider<PaymentMethodsController, List<PaymentMethod>>((ref) {
  final storage = ref.watch(appStorageProvider);
  return PaymentMethodsController(storage);
});

class PaymentMethodsController extends StateNotifier<List<PaymentMethod>> {
  PaymentMethodsController(this._storage) : super(_loadFromStorage(_storage));

  final AppStorage _storage;

  static List<PaymentMethod> _loadFromStorage(AppStorage storage) {
    final raw = storage.paymentMethodsJson;
    if (raw == null || raw.isEmpty) {
      return const [];
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded
          .whereType<Map<String, dynamic>>()
          .map(PaymentMethod.fromJson)
          .toList();
    } catch (_) {
      return const [];
    }
  }

  Future<void> addMethod(PaymentMethod method, {bool makeDefault = false}) async {
    var next = [...state, method];
    if (makeDefault || next.length == 1) {
      next = _setDefault(next, method.id);
    }
    state = next;
    await _persist();
  }

  Future<void> setDefault(String id) async {
    state = _setDefault(state, id);
    await _persist();
  }

  Future<void> removeMethod(String id) async {
    final remaining = state.where((method) => method.id != id).toList();
    if (remaining.isNotEmpty && !remaining.any((method) => method.isDefault)) {
      remaining[0] = remaining[0].copyWith(isDefault: true);
    }
    state = remaining;
    await _persist();
  }

  Future<void> updateMethod(PaymentMethod updated) async {
    state = [
      for (final method in state)
        if (method.id == updated.id) updated else method,
    ];
    await _persist();
  }

  Future<void> _persist() async {
    if (state.isEmpty) {
      await _storage.setPaymentMethodsJson(null);
      return;
    }
    final encoded = jsonEncode(state.map((e) => e.toJson()).toList());
    await _storage.setPaymentMethodsJson(encoded);
  }

  List<PaymentMethod> _setDefault(List<PaymentMethod> methods, String id) {
    return [
      for (final method in methods)
        method.copyWith(isDefault: method.id == id),
    ];
  }
}
