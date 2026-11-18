import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../../../core/storage/app_storage.dart';

final homeNavigationControllerProvider =
    StateNotifierProvider<HomeNavigationController, int>((ref) {
  final storage = ref.watch(appStorageProvider);
  return HomeNavigationController(storage);
});

class HomeNavigationController extends StateNotifier<int> {
  HomeNavigationController(this._storage)
      : super(_initialIndex(_storage));

  final AppStorage _storage;

  static int _initialIndex(AppStorage storage) {
    final stored = storage.homeTabIndex;
    return stored < 0 ? 0 : stored;
  }

  Future<void> setIndex(int index) async {
    if (index < 0) {
      return;
    }
    if (state == index) {
      return;
    }
    state = index;
    await _storage.setHomeTabIndex(index);
  }
}
