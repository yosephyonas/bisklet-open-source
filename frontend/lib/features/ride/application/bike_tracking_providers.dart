import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/network_providers.dart';
import '../services/bike_tracking_service.dart';

final bikeTrackingServiceProvider = Provider<BikeTrackingService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return BikeTrackingService(apiClient: apiClient);
});

final bikeLocationsStreamProvider =
    StreamProvider.autoDispose<List<BikeLocation>>((ref) {
  final service = ref.watch(bikeTrackingServiceProvider);
  final stream = service.bikeLocations();
  return stream;
});
