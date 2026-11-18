import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/network_providers.dart';
import '../domain/ride_receipt_models.dart';
import '../services/ride_receipt_service.dart';

final rideReceiptServiceProvider = Provider<RideReceiptService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return RideReceiptService(apiClient: apiClient);
});

final rideReceiptProvider = FutureProvider.autoDispose.family<RideReceipt, String>((ref, rideId) async {
  final service = ref.watch(rideReceiptServiceProvider);
  return service.fetchRide(rideId);
});
