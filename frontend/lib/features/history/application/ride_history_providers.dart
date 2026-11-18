import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/network_providers.dart';
import '../services/ride_history_service.dart';
import '../domain/ride_history_models.dart';

final rideHistoryServiceProvider = Provider<RideHistoryService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return RideHistoryService(apiClient: apiClient);
});

final rideHistoryProvider =
    FutureProvider.autoDispose<List<RideHistoryRide>>((ref) async {
  final service = ref.watch(rideHistoryServiceProvider);
  return service.fetchCompletedRides();
});
