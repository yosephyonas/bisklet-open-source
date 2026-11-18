import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/app_providers.dart';
import '../../../core/storage/app_storage.dart';

final rideHistoryFilterProvider =
    StateNotifierProvider<RideHistoryFilterController, int>((ref) {
  final storage = ref.watch(appStorageProvider);
  return RideHistoryFilterController(storage);
});

class RideHistoryFilterController extends StateNotifier<int> {
  RideHistoryFilterController(this._storage)
      : super(_storage.rideHistoryFilter);

  final AppStorage _storage;

  Future<void> setFilter(int index) async {
    if (state == index) {
      return;
    }
    state = index;
    await _storage.setRideHistoryFilter(index);
  }
}
