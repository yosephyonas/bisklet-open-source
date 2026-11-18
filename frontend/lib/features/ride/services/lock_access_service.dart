import '../../../core/config/api_endpoints.dart';
import '../../../core/network/api_client.dart';

class LockScanResult {
  const LockScanResult({
    required this.imei,
    required this.scanToken,
    this.lockStatus,
    this.batteryPercent,
  });

  final String imei;
  final String scanToken;
  final int? lockStatus;
  final int? batteryPercent;

  factory LockScanResult.fromJson(Map<String, dynamic> json) {
    return LockScanResult(
      imei: (json['imei'] ?? '').toString(),
      scanToken: (json['scan_token'] ?? '').toString(),
      lockStatus: (json['lock_status'] as num?)?.toInt(),
      batteryPercent: (json['battery_percent'] as num?)?.toInt(),
    );
  }
}

class LockUnlockResult {
  const LockUnlockResult({
    required this.status,
    this.requestId,
    this.result,
  });

  final String status;
  final String? requestId;
  final Map<String, dynamic>? result;

  bool get isSuccess => status.toLowerCase() == 'success';

  factory LockUnlockResult.fromJson(Map<String, dynamic> json) {
    return LockUnlockResult(
      status: (json['status'] ?? '').toString(),
      requestId: json['request_id']?.toString(),
      result: json['result'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['result'] as Map<String, dynamic>)
          : null,
    );
  }
}

class LockAccessService {
  LockAccessService({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<LockScanResult> scanLock(String lockCode) async {
    final response = await _apiClient.post(ApiEndpoints.lockScan, {
      'lock_code': lockCode.trim(),
    });
    return LockScanResult.fromJson(response);
  }

  Future<LockUnlockResult> unlockLock({
    required String imei,
    required String scanToken,
  }) async {
    final response = await _apiClient.post(
      ApiEndpoints.lockUnlock(imei),
      {'scan_token': scanToken},
    );
    return LockUnlockResult.fromJson(response);
  }
}
