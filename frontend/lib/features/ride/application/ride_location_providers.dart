import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/network_providers.dart';
import '../services/ride_api_service.dart';
import '../services/ride_location_service.dart';
import 'ride_api_providers.dart';

final rideLocationServiceProvider = Provider<RideLocationService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final rideApi = ref.watch(rideApiServiceProvider);
  return RideLocationService(apiClient: apiClient, rideApiService: rideApi);
});
