import '../../../core/config/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../domain/ride_history_models.dart';

class RideHistoryService {
  RideHistoryService({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<List<RideHistoryRide>> fetchCompletedRides({int perPage = 30}) async {
    final response = await _apiClient.get(
      '${ApiEndpoints.rides}?per_page=$perPage&status=completed',
    );
    final data = response['data'];
    if (data is List) {
      return data
          .whereType<Map<String, dynamic>>()
          .map(RideHistoryRide.fromJson)
          .toList();
    }
    return const [];
  }
}
