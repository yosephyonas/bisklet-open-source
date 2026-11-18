import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/network_providers.dart';
import '../services/lock_access_service.dart';
import '../services/ride_api_service.dart';
import '../services/ride_flow_service.dart';

final lockAccessServiceProvider = Provider<LockAccessService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return LockAccessService(apiClient: apiClient);
});

final rideApiServiceProvider = Provider<RideApiService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return RideApiService(apiClient: apiClient);
});

final rideFlowServiceProvider = Provider<RideFlowService>((ref) {
  final lockAccess = ref.watch(lockAccessServiceProvider);
  final rideApi = ref.watch(rideApiServiceProvider);
  return RideFlowService(
    lockAccessService: lockAccess,
    rideApiService: rideApi,
  );
});
