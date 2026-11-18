import 'package:flutter/foundation.dart';
import 'package:hive_flutter/hive_flutter.dart';

import '../logging/app_logger.dart';

class AppStorage {
  AppStorage(this._box, {AppLogger? logger}) : _logger = logger;

  final Box<dynamic> _box;
  final AppLogger? _logger;

  static const _hasSeenOnboardingKey = 'hasSeenOnboarding';
  static const _fydaKey = 'fydaNumber';
  static const _fullNameKey = 'fullName';
  static const _homeTabIndexKey = 'homeTabIndex';
  static const _rideHistoryFilterKey = 'rideHistoryFilter';
  static const _autoTopUpEnabledKey = 'autoTopUpEnabled';
  static const _billingEmailKey = 'billingEmail';
  static const _rideSessionStatusKey = 'rideSessionStatus';
  static const _rideSessionBikeKey = 'rideSessionBikeId';
  static const _rideSessionRideIdKey = 'rideSessionRideId';
  static const _rideSessionLockImeiKey = 'rideSessionLockImei';
  static const _rideSessionPerMinuteRateKey = 'rideSessionPerMinuteRate';
  static const _rideSessionUnlockFeeKey = 'rideSessionUnlockFee';
  static const _rideReservationExpiryKey = 'rideReservationExpiry';
  static const _rideStartedAtKey = 'rideStartedAt';
  static const _ridePausedAtKey = 'ridePausedAt';
  static const _rideSessionStatusIdle = 'idle';
  static const _locationPermissionKey = 'locationPermissionGranted';
  static const _notificationPermissionKey = 'notificationPermissionGranted';
  static const _paymentMethodsKey = 'paymentMethods';
  static const _pushRideUpdatesKey = 'pushRideUpdatesEnabled';
  static const _pushPromotionsKey = 'pushPromotionsEnabled';
  static const _emailReceiptsKey = 'emailReceiptsEnabled';
  static const _themeModeKey = 'themeMode';
  static const _appLocaleCodeKey = 'appLocaleCode';
  static const _themeModeSystem = 'system';

  static const defaultBillingEmail = 'billing@bisklet.app';

  static const _authTokenKey = 'authToken';

  String? get authToken {
    final value = _box.get(_authTokenKey) as String?;
    if (value == null || value.trim().isEmpty) return null;
    return value.trim();
  }

  Future<void> setAuthToken(String token) async {
    final trimmed = token.trim();
    if (trimmed.isEmpty) {
      await _safeDelete(_authTokenKey);
      return;
    }
    await _safePut(_authTokenKey, trimmed);
  }

  Future<void> clearAuthToken() async => _safeDelete(_authTokenKey);

  ValueListenable<Box<dynamic>> get listenable => _box.listenable();

  String? get paymentMethodsJson {
    final value = _box.get(_paymentMethodsKey) as String?;
    if (value == null || value.trim().isEmpty) return null;
    return value;
  }

  Future<void> setPaymentMethodsJson(String? value) async {
    if (value == null || value.trim().isEmpty) {
      await _safeDelete(_paymentMethodsKey);
      return;
    }
    await _safePut(_paymentMethodsKey, value);
  }

  bool get isLocationPermissionGranted =>
      _box.get(_locationPermissionKey, defaultValue: false) as bool;

  bool get isNotificationPermissionGranted =>
      _box.get(_notificationPermissionKey, defaultValue: false) as bool;

  bool get pushRideUpdatesEnabled =>
      _box.get(_pushRideUpdatesKey, defaultValue: true) as bool;

  bool get pushPromotionsEnabled =>
      _box.get(_pushPromotionsKey, defaultValue: false) as bool;

  bool get emailReceiptsEnabled =>
      _box.get(_emailReceiptsKey, defaultValue: true) as bool;

  String get themeMode =>
      _box.get(_themeModeKey, defaultValue: _themeModeSystem) as String;

  String? get appLocaleCode {
    final value = _box.get(_appLocaleCodeKey) as String?;
    if (value == null || value.trim().isEmpty) {
      return null;
    }
    return value.trim();
  }

  Future<void> setLocationPermissionGranted(bool granted) =>
      _safePut(_locationPermissionKey, granted);

  Future<void> setNotificationPermissionGranted(bool granted) =>
      _safePut(_notificationPermissionKey, granted);

  Future<void> setPushRideUpdatesEnabled(bool value) =>
      _safePut(_pushRideUpdatesKey, value);

  Future<void> setPushPromotionsEnabled(bool value) =>
      _safePut(_pushPromotionsKey, value);

  Future<void> setEmailReceiptsEnabled(bool value) =>
      _safePut(_emailReceiptsKey, value);

  Future<void> setThemeMode(String value) => _safePut(_themeModeKey, value);

  Future<void> setAppLocaleCode(String? code) async {
    final trimmed = code?.trim();
    if (trimmed == null || trimmed.isEmpty) {
      await _safeDelete(_appLocaleCodeKey);
      return;
    }
    await _safePut(_appLocaleCodeKey, trimmed);
  }

  bool get hasSeenOnboarding =>
      _box.get(_hasSeenOnboardingKey, defaultValue: false) as bool;

  String? get fydaNumber {
    final value = _box.get(_fydaKey) as String?;
    if (value == null || value.trim().isEmpty) {
      return null;
    }
    return value.trim();
  }

  String? get fullName {
    final value = _box.get(_fullNameKey) as String?;
    if (value == null || value.trim().isEmpty) {
      return null;
    }
    return value.trim();
  }

  int get homeTabIndex =>
      (_box.get(_homeTabIndexKey, defaultValue: 0) as num).toInt();

  int get rideHistoryFilter =>
      (_box.get(_rideHistoryFilterKey, defaultValue: 0) as num).toInt();

  bool get autoTopUpEnabled =>
      _box.get(_autoTopUpEnabledKey, defaultValue: true) as bool;

  String get billingEmail {
    final value = _box.get(_billingEmailKey) as String?;
    if (value == null || value.trim().isEmpty) {
      return defaultBillingEmail;
    }
    return value.trim();
  }

  String get rideSessionStatus =>
      _box.get(_rideSessionStatusKey, defaultValue: _rideSessionStatusIdle)
          as String;

  String? get rideSessionBikeId {
    final value = _box.get(_rideSessionBikeKey) as String?;
    if (value == null || value.trim().isEmpty) {
      return null;
    }
    return value.trim();
  }

  String? get rideSessionRideId {
    final value = _box.get(_rideSessionRideIdKey) as String?;
    if (value == null || value.trim().isEmpty) {
      return null;
    }
    return value.trim();
  }

  String? get rideSessionLockImei {
    final value = _box.get(_rideSessionLockImeiKey) as String?;
    if (value == null || value.trim().isEmpty) {
      return null;
    }
    return value.trim();
  }

  double? get rideSessionPerMinuteRate =>
      _readDouble(_rideSessionPerMinuteRateKey);

  double? get rideSessionUnlockFee => _readDouble(_rideSessionUnlockFeeKey);

  DateTime? get rideReservationExpiry =>
      _readDateTime(_rideReservationExpiryKey);

  DateTime? get rideStartedAt => _readDateTime(_rideStartedAtKey);

  DateTime? get ridePausedAt => _readDateTime(_ridePausedAtKey);

  Future<void> setHasSeenOnboarding(bool value) =>
      _safePut(_hasSeenOnboardingKey, value);

  Future<void> setFydaNumber(String fyda) async {
    final trimmed = fyda.trim();
    if (trimmed.isEmpty) {
      await _safeDelete(_fydaKey);
      return;
    }
    await _safePut(_fydaKey, trimmed);
  }

  Future<void> clearFydaNumber() => _safeDelete(_fydaKey);

  Future<void> setFullName(String name) async {
    final trimmed = name.trim();
    if (trimmed.isEmpty) {
      await _safeDelete(_fullNameKey);
      return;
    }
    await _safePut(_fullNameKey, trimmed);
  }

  Future<void> clearFullName() => _safeDelete(_fullNameKey);

  Future<void> setHomeTabIndex(int index) => _safePut(_homeTabIndexKey, index);

  Future<void> setRideHistoryFilter(int index) =>
      _safePut(_rideHistoryFilterKey, index);

  Future<void> setAutoTopUpEnabled(bool value) =>
      _safePut(_autoTopUpEnabledKey, value);

  Future<void> setBillingEmail(String email) async {
    final trimmed = email.trim();
    if (trimmed.isEmpty) {
      await _safeDelete(_billingEmailKey);
      return;
    }
    await _safePut(_billingEmailKey, trimmed);
  }

  Future<void> clearBillingEmail() => _safeDelete(_billingEmailKey);

  Future<void> setRideSessionStatus(String status) =>
      _safePut(_rideSessionStatusKey, status);

  Future<void> setRideSessionBikeId(String? bikeId) async {
    final trimmed = bikeId?.trim();
    if (trimmed == null || trimmed.isEmpty) {
      await _safeDelete(_rideSessionBikeKey);
      return;
    }
    await _safePut(_rideSessionBikeKey, trimmed);
  }

  Future<void> setRideSessionRideId(String? rideId) async {
    final trimmed = rideId?.trim();
    if (trimmed == null || trimmed.isEmpty) {
      await _safeDelete(_rideSessionRideIdKey);
      return;
    }
    await _safePut(_rideSessionRideIdKey, trimmed);
  }

  Future<void> setRideSessionLockImei(String? imei) async {
    final trimmed = imei?.trim();
    if (trimmed == null || trimmed.isEmpty) {
      await _safeDelete(_rideSessionLockImeiKey);
      return;
    }
    await _safePut(_rideSessionLockImeiKey, trimmed);
  }

  Future<void> setRideSessionPerMinuteRate(double? rate) =>
      _writeDouble(_rideSessionPerMinuteRateKey, rate);

  Future<void> setRideSessionUnlockFee(double? fee) =>
      _writeDouble(_rideSessionUnlockFeeKey, fee);

  Future<void> setRideReservationExpiry(DateTime? timestamp) =>
      _writeDateTime(_rideReservationExpiryKey, timestamp);

  Future<void> setRideStartedAt(DateTime? timestamp) =>
      _writeDateTime(_rideStartedAtKey, timestamp);

  Future<void> setRidePausedAt(DateTime? timestamp) =>
      _writeDateTime(_ridePausedAtKey, timestamp);

  Future<void> clearRideSession() async {
    await Future.wait([
      setRideSessionStatus(_rideSessionStatusIdle),
      _safeDelete(_rideSessionBikeKey),
      _safeDelete(_rideSessionRideIdKey),
      _safeDelete(_rideSessionLockImeiKey),
      _safeDelete(_rideSessionPerMinuteRateKey),
      _safeDelete(_rideSessionUnlockFeeKey),
      _safeDelete(_rideReservationExpiryKey),
      _safeDelete(_rideStartedAtKey),
      _safeDelete(_ridePausedAtKey),
    ]);
  }

  DateTime? _readDateTime(String key) {
    final raw = _box.get(key) as String?;
    if (raw == null || raw.isEmpty) {
      return null;
    }
    return DateTime.tryParse(raw);
  }

  Future<void> _writeDateTime(String key, DateTime? value) async {
    if (value == null) {
      await _safeDelete(key);
      return;
    }
    await _safePut(key, value.toIso8601String());
  }

  double? _readDouble(String key) {
    final raw = _box.get(key);
    if (raw == null) return null;
    if (raw is num) return raw.toDouble();
    if (raw is String) return double.tryParse(raw);
    return null;
  }

  Future<void> _writeDouble(String key, double? value) async {
    if (value == null) {
      await _safeDelete(key);
      return;
    }
    await _safePut(key, value);
  }

  Future<void> _safePut(String key, dynamic value) async {
    try {
      await _box.put(key, value);
    } catch (error, stackTrace) {
      _logger?.logError(
        'AppStorage put failed',
        error: error,
        stackTrace: stackTrace,
        context: {'key': key, 'value': value},
      );
      rethrow;
    }
  }

  Future<void> _safeDelete(String key) async {
    try {
      await _box.delete(key);
    } catch (error, stackTrace) {
      _logger?.logError(
        'AppStorage delete failed',
        error: error,
        stackTrace: stackTrace,
        context: {'key': key},
      );
      rethrow;
    }
  }
}
