import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/app_providers.dart';
import '../storage/app_storage.dart';

final themeModeProvider =
    StateNotifierProvider<ThemeModeController, ThemeMode>((ref) {
  final storage = ref.watch(appStorageProvider);
  return ThemeModeController(storage);
});

class ThemeModeController extends StateNotifier<ThemeMode> {
  ThemeModeController(this._storage)
      : super(_parseThemeMode(_storage.themeMode));

  final AppStorage _storage;

  Future<void> setThemeMode(ThemeMode mode) async {
    if (state == mode) return;
    state = mode;
    await _storage.setThemeMode(_serializeThemeMode(mode));
  }

  static ThemeMode _parseThemeMode(String raw) {
    switch (raw) {
      case 'light':
        return ThemeMode.light;
      case 'dark':
        return ThemeMode.dark;
      default:
        return ThemeMode.system;
    }
  }

  static String _serializeThemeMode(ThemeMode mode) {
    switch (mode) {
      case ThemeMode.light:
        return 'light';
      case ThemeMode.dark:
        return 'dark';
      case ThemeMode.system:
      default:
        return 'system';
    }
  }
}
