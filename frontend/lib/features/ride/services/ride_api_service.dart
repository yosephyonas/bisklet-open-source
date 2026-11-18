import 'dart:async';

import '../../../core/config/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../domain/ride_summary.dart';

class RideApiService {
  RideApiService({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<RideSummary?> fetchActiveRide({int perPage = 1}) async {
    final response = await _apiClient.get(
      '${ApiEndpoints.rides}?per_page=$perPage&status=active',
    );
    final data = response['data'];
    if (data is List && data.isNotEmpty) {
      final first = data.first;
      if (first is Map<String, dynamic>) {
        return RideSummary.fromJson(first);
      }
    }
    return null;
  }

  Future<RideSummary?> waitForActiveRide({
    Duration timeout = const Duration(seconds: 10),
    Duration interval = const Duration(seconds: 1),
  }) async {
    final endTime = DateTime.now().add(timeout);
    while (DateTime.now().isBefore(endTime)) {
      final active = await fetchActiveRide();
      if (active != null) {
        return active;
      }
      await Future<void>.delayed(interval);
    }
    return null;
  }

  Future<RideSummary?> fetchLatestCompletedRide({int perPage = 1}) async {
    final response = await _apiClient.get(
      '${ApiEndpoints.rides}?per_page=$perPage&status=completed',
    );
    final data = response['data'];
    if (data is List && data.isNotEmpty) {
      final first = data.first;
      if (first is Map<String, dynamic>) {
        return RideSummary.fromJson(first);
      }
    }
    return null;
  }

  Future<RideSummary> completeRide(
    String rideId, {
    double? distanceMeters,
  }) async {
    final payload = <String, dynamic>{};
    if (distanceMeters != null && distanceMeters >= 0) {
      payload['distance_m'] = distanceMeters;
    }

    final response = await _apiClient.post(
      ApiEndpoints.rideComplete(rideId),
      payload,
    );

    final data = response['data'];
    if (data is Map<String, dynamic>) {
      return RideSummary.fromJson(data);
    }
    return RideSummary.fromJson(response);
  }
}
