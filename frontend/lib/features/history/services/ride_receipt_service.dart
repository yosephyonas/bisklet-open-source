import '../../../core/config/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../domain/ride_receipt_models.dart';

class RideReceiptService {
  RideReceiptService({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<RideReceipt> fetchRide(String rideId) async {
    final response = await _apiClient.get(ApiEndpoints.ride(rideId));
    final data = response['data'];
    if (data is Map<String, dynamic>) {
      return RideReceipt.fromJson(data);
    }
    throw ApiException('Ride receipt not available');
  }
}
