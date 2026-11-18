import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/logging/app_logger.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/storage/app_storage.dart';

final rideSessionControllerProvider =
    StateNotifierProvider<RideSessionController, RideSessionState>((ref) {
      final storage = ref.watch(appStorageProvider);
      final logger = ref.watch(appLoggerProvider);
      return RideSessionController(storage, logger: logger);
    });

enum RideSessionStatus { idle, reserved, inRide, paused }

extension RideSessionStatusX on RideSessionStatus {
  String get storageValue => name;
}

RideSessionStatus _statusFromStorage(String value) {
  return RideSessionStatus.values.firstWhere(
    (status) => status.storageValue == value,
    orElse: () => RideSessionStatus.idle,
  );
}

class RideSessionState {
  const RideSessionState({
    required this.status,
    this.bikeId,
    this.rideId,
    this.lockImei,
    this.perMinuteRate,
    this.unlockFee,
    this.reservationExpiry,
    this.rideStartedAt,
    this.pausedAt,
  });

  final RideSessionStatus status;
  final String? bikeId;
  final String? rideId;
  final String? lockImei;
  final double? perMinuteRate;
  final double? unlockFee;
  final DateTime? reservationExpiry;
  final DateTime? rideStartedAt;
  final DateTime? pausedAt;

  static const _sentinel = Object();

  bool get hasReservation => status == RideSessionStatus.reserved;
  bool get isRiding =>
      status == RideSessionStatus.inRide || status == RideSessionStatus.paused;
  bool get isPaused => status == RideSessionStatus.paused;
  bool get isIdle => status == RideSessionStatus.idle;

  Duration? get reservationTimeLeft {
    if (reservationExpiry == null) {
      return null;
    }
    final diff = reservationExpiry!.difference(DateTime.now());
    if (diff.isNegative) {
      return Duration.zero;
    }
    return diff;
  }

  RideSessionState copyWith({
    RideSessionStatus? status,
    Object? bikeId = _sentinel,
    Object? rideId = _sentinel,
    Object? lockImei = _sentinel,
    Object? perMinuteRate = _sentinel,
    Object? unlockFee = _sentinel,
    Object? reservationExpiry = _sentinel,
    Object? rideStartedAt = _sentinel,
    Object? pausedAt = _sentinel,
  }) {
    return RideSessionState(
      status: status ?? this.status,
      bikeId: identical(bikeId, _sentinel) ? this.bikeId : bikeId as String?,
      rideId: identical(rideId, _sentinel) ? this.rideId : rideId as String?,
      lockImei: identical(lockImei, _sentinel)
          ? this.lockImei
          : lockImei as String?,
      perMinuteRate: identical(perMinuteRate, _sentinel)
          ? this.perMinuteRate
          : perMinuteRate as double?,
      unlockFee: identical(unlockFee, _sentinel)
          ? this.unlockFee
          : unlockFee as double?,
      reservationExpiry: identical(reservationExpiry, _sentinel)
          ? this.reservationExpiry
          : reservationExpiry as DateTime?,
      rideStartedAt: identical(rideStartedAt, _sentinel)
          ? this.rideStartedAt
          : rideStartedAt as DateTime?,
      pausedAt: identical(pausedAt, _sentinel)
          ? this.pausedAt
          : pausedAt as DateTime?,
    );
  }

  factory RideSessionState.fromStorage(AppStorage storage) {
    return RideSessionState(
      status: _statusFromStorage(storage.rideSessionStatus),
      bikeId: storage.rideSessionBikeId,
      rideId: storage.rideSessionRideId,
      lockImei: storage.rideSessionLockImei,
      perMinuteRate: storage.rideSessionPerMinuteRate,
      unlockFee: storage.rideSessionUnlockFee,
      reservationExpiry: storage.rideReservationExpiry,
      rideStartedAt: storage.rideStartedAt,
      pausedAt: storage.ridePausedAt,
    );
  }
}

class RideSessionController extends StateNotifier<RideSessionState> {
  RideSessionController(AppStorage storage, {required AppLogger logger})
    : _storage = storage,
      _logger = logger,
      super(RideSessionState.fromStorage(storage)) {
    _restoreReservationTimer();
  }

  final AppStorage _storage;
  final AppLogger _logger;
  Timer? _expirationTimer;

  Future<void> reserveBike(
    String bikeId, {
    Duration holdDuration = const Duration(minutes: 10),
    String? lockImei,
  }) async {
    if (state.isRiding) {
      _logger.logInfo(
        'reserve_bike_ignored',
        context: {'reason': 'already_in_ride', 'bikeId': bikeId},
      );
      return;
    }

    final trimmed = bikeId.trim();
    if (trimmed.isEmpty) {
      _logger.logInfo('reserve_bike_ignored', context: {'reason': 'empty_id'});
      return;
    }

    final expiry = DateTime.now().add(holdDuration);
    _clearExpirationTimer();
    state = RideSessionState(
      status: RideSessionStatus.reserved,
      bikeId: trimmed,
      rideId: null,
      lockImei: lockImei?.trim().isEmpty == true ? null : lockImei?.trim(),
      perMinuteRate: null,
      unlockFee: null,
      reservationExpiry: expiry,
      rideStartedAt: null,
      pausedAt: null,
    );

    try {
      await Future.wait([
        _storage.setRideSessionStatus(RideSessionStatus.reserved.storageValue),
        _storage.setRideSessionBikeId(trimmed),
        _storage.setRideSessionRideId(null),
        _storage.setRideSessionLockImei(lockImei),
        _storage.setRideSessionPerMinuteRate(null),
        _storage.setRideSessionUnlockFee(null),
        _storage.setRideReservationExpiry(expiry),
        _storage.setRideStartedAt(null),
        _storage.setRidePausedAt(null),
      ]);
      _scheduleExpiration(expiry);
      _logEvent('ride_reservation_created', {
        'bikeId': trimmed,
        'holdDurationSeconds': holdDuration.inSeconds,
      });
    } catch (error, stackTrace) {
      _logger.logError(
        'reserve_bike_failed',
        error: error,
        stackTrace: stackTrace,
        context: {'bikeId': trimmed},
      );
      rethrow;
    }
  }

  Future<void> cancelReservation({
    String reason = 'user_cancel',
    bool log = true,
  }) async {
    if (state.isIdle) {
      return;
    }
    final previousBike = state.bikeId;
    _clearExpirationTimer();
    state = const RideSessionState(status: RideSessionStatus.idle);
    try {
      await _storage.clearRideSession();
      if (log) {
        _logEvent('ride_reservation_cancelled', {
          'bikeId': previousBike,
          'reason': reason,
        });
      }
    } catch (error, stackTrace) {
      _logger.logError(
        'cancel_reservation_failed',
        error: error,
        stackTrace: stackTrace,
        context: {'bikeId': previousBike, 'reason': reason},
      );
      rethrow;
    }
  }

  Future<void> startRide({DateTime? startedAt}) async {
    final bikeId = state.bikeId;
    if (bikeId == null) {
      _logger.logInfo('start_ride_ignored', context: {'reason': 'no_bike'});
      return;
    }
    _clearExpirationTimer();
    final startTime = startedAt ?? DateTime.now();
    state = state.copyWith(
      status: RideSessionStatus.inRide,
      reservationExpiry: null,
      rideStartedAt: startTime,
      pausedAt: null,
    );

    try {
      await Future.wait([
      _storage.setRideSessionStatus(RideSessionStatus.inRide.storageValue),
      _storage.setRideSessionBikeId(bikeId),
      _storage.setRideReservationExpiry(null),
      _storage.setRideStartedAt(startTime),
      _storage.setRidePausedAt(null),
    ]);
      _logEvent('ride_session_started', {
        'bikeId': bikeId,
        'startTimestamp': startTime.toIso8601String(),
      });
    } catch (error, stackTrace) {
      _logger.logError(
        'start_ride_failed',
        error: error,
        stackTrace: stackTrace,
        context: {'bikeId': bikeId},
      );
      rethrow;
    }
  }

  Future<void> attachRideInfo({
    String? rideId,
    String? lockImei,
    double? perMinuteRate,
    double? unlockFee,
  }) async {
    final nextRideId = rideId?.trim().isEmpty == true ? null : rideId?.trim();
    final nextLockImei =
        lockImei?.trim().isEmpty == true ? null : lockImei?.trim();

    state = state.copyWith(
      rideId: nextRideId ?? state.rideId,
      lockImei: nextLockImei ?? state.lockImei,
      perMinuteRate: perMinuteRate ?? state.perMinuteRate,
      unlockFee: unlockFee ?? state.unlockFee,
    );

    await Future.wait([
      if (rideId != null) _storage.setRideSessionRideId(rideId),
      if (lockImei != null) _storage.setRideSessionLockImei(lockImei),
      if (perMinuteRate != null)
        _storage.setRideSessionPerMinuteRate(perMinuteRate),
      if (unlockFee != null) _storage.setRideSessionUnlockFee(unlockFee),
    ]);
  }

  Future<void> endRide() async {
    final bikeId = state.bikeId;
    final startedAt = state.rideStartedAt;
    await cancelReservation(reason: 'ride_completed', log: false);
    _logEvent('ride_session_ended', {
      'bikeId': bikeId,
      'durationSeconds': startedAt == null
          ? null
          : DateTime.now().difference(startedAt).inSeconds,
    });
  }

  void _restoreReservationTimer() {
    final expiry = state.reservationExpiry;
    if (state.status == RideSessionStatus.reserved && expiry != null) {
      _scheduleExpiration(expiry);
    }
  }

  void _scheduleExpiration(DateTime expiry) {
    final diff = expiry.difference(DateTime.now());
    if (diff.isNegative) {
      _handleReservationExpired();
      return;
    }
    _expirationTimer = Timer(diff, _handleReservationExpired);
  }

  void _handleReservationExpired() {
    final bikeId = state.bikeId;
    _clearExpirationTimer();
    state = const RideSessionState(status: RideSessionStatus.idle);
    unawaited(_storage.clearRideSession());
    _logEvent('ride_reservation_expired', {'bikeId': bikeId});
  }

  Future<void> pauseRide() async {
    if (!state.isRiding || state.isPaused) {
      return;
    }
    final pausedAt = DateTime.now();
    state = state.copyWith(
      status: RideSessionStatus.paused,
      pausedAt: pausedAt,
    );
    await Future.wait([
      _storage.setRideSessionStatus(RideSessionStatus.paused.storageValue),
      _storage.setRidePausedAt(pausedAt),
    ]);
    _logEvent('ride_session_paused', {
      'bikeId': state.bikeId,
      'pausedAt': pausedAt.toIso8601String(),
    });
  }

  Future<void> resumeRide() async {
    if (!state.isPaused) {
      return;
    }
    final pausedAt = state.pausedAt;
    final startedAt = state.rideStartedAt;
    final now = DateTime.now();
    final adjustedStart =
        (pausedAt != null && startedAt != null)
            ? startedAt.add(now.difference(pausedAt))
            : startedAt;
    state = state.copyWith(
      status: RideSessionStatus.inRide,
      rideStartedAt: adjustedStart,
      pausedAt: null,
    );
    await Future.wait([
      _storage.setRideSessionStatus(RideSessionStatus.inRide.storageValue),
      _storage.setRideStartedAt(adjustedStart),
      _storage.setRidePausedAt(null),
    ]);
    _logEvent('ride_session_resumed', {
      'bikeId': state.bikeId,
      'resumedAt': now.toIso8601String(),
    });
  }

  void _clearExpirationTimer() {
    _expirationTimer?.cancel();
    _expirationTimer = null;
  }

  void _logEvent(String name, Map<String, dynamic> parameters) {
    final filtered = <String, dynamic>{};
    for (final entry in parameters.entries) {
      final value = entry.value;
      if (value != null) {
        filtered[entry.key] = value;
      }
    }
    _logger.logEvent(name, parameters: filtered);
  }

  @override
  void dispose() {
    _clearExpirationTimer();
    super.dispose();
  }
}
