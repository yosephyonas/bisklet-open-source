import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/app_providers.dart';
import '../i18n/app_i18n.dart';

class LocaleController extends StateNotifier<Locale?> {
  LocaleController(this.ref) : super(_readInitial(ref));

  final Ref ref;

  static Locale? _readInitial(Ref ref) {
    final code = ref.read(appStorageProvider).appLocaleCode?.toLowerCase();
    if (code == null || code.isEmpty) return null;
    if (!AppI18n.supportedLanguageCodes.contains(code)) return null;
    return Locale(code);
  }

  Future<void> setLocaleCode(String? code) async {
    final normalized = code?.trim().toLowerCase();
    if (normalized == null || normalized.isEmpty) {
      await ref.read(appStorageProvider).setAppLocaleCode(null);
      state = null;
      return;
    }
    if (!AppI18n.supportedLanguageCodes.contains(normalized)) {
      await ref.read(appStorageProvider).setAppLocaleCode(null);
      state = null;
      return;
    }
    await ref.read(appStorageProvider).setAppLocaleCode(normalized);
    state = Locale(normalized);
  }
}

final appLocaleControllerProvider =
    StateNotifierProvider<LocaleController, Locale?>((ref) {
      return LocaleController(ref);
    });
