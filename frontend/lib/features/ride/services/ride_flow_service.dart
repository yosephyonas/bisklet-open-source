import '../domain/ride_summary.dart';
import 'lock_access_service.dart';
import 'ride_api_service.dart';

class RideStartResult {
  RideStartResult({
    required this.scan,
    required this.unlock,
    this.activeRide,
  });

  final LockScanResult scan;
  final LockUnlockResult unlock;
  final RideSummary? activeRide;

  bool get isSuccess => unlock.isSuccess;
}

class RideFlowService {
  RideFlowService({
    required LockAccessService lockAccessService,
    required RideApiService rideApiService,
  })  : _lockAccessService = lockAccessService,
        _rideApiService = rideApiService;

  final LockAccessService _lockAccessService;
  final RideApiService _rideApiService;

  Future<RideStartResult> startRideFromLockCode(String lockCode) async {
    final scan = await _lockAccessService.scanLock(lockCode);
    final unlock = await _lockAccessService.unlockLock(
      imei: scan.imei,
      scanToken: scan.scanToken,
    );

    RideSummary? activeRide;
    if (unlock.isSuccess) {
      activeRide = await _rideApiService.waitForActiveRide();
    }

    return RideStartResult(scan: scan, unlock: unlock, activeRide: activeRide);
  }

  Future<RideSummary?> completeRide({
    String? rideId,
    double? distanceMeters,
  }) async {
    var id = rideId;
    if (id == null || id.trim().isEmpty) {
      final active = await _rideApiService.fetchActiveRide();
      id = active?.id;
    }
    if (id == null || id.trim().isEmpty) {
      return null;
    }
    return _rideApiService.completeRide(id, distanceMeters: distanceMeters);
  }
}
