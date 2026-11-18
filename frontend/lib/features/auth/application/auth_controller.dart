import 'package:bisklet/core/network/api_client.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/api_endpoints.dart';
import '../../../core/providers/app_providers.dart';
import '../../../core/storage/app_storage.dart';

class AuthState {
  const AuthState({
    required this.fydaNumber,
    required this.fullName,
    required this.hasSeenOnboarding,
  });

  final String? fydaNumber;
  final String? fullName;
  final bool hasSeenOnboarding;

  bool get isAuthenticated => fydaNumber != null;
  String get displayName => fullName ?? 'Bisklet Rider';

  AuthState copyWith({
    String? fydaNumber,
    String? fullName,
    bool? hasSeenOnboarding,
  }) {
    return AuthState(
      fydaNumber: fydaNumber ?? this.fydaNumber,
      fullName: fullName ?? this.fullName,
      hasSeenOnboarding: hasSeenOnboarding ?? this.hasSeenOnboarding,
    );
  }

  factory AuthState.initial(AppStorage storage) {
    return AuthState(
      fydaNumber: storage.fydaNumber,
      fullName: storage.fullName,
      hasSeenOnboarding: storage.hasSeenOnboarding,
    );
  }
}

class AuthController extends StateNotifier<AuthState> {
  // make the field final and initialize it properly
  final AppStorage _storage;

  // Normal constructor param + initializer list:
  AuthController(AppStorage storage)
    : _storage = storage,
      super(AuthState.initial(storage));

  Future<void> markOnboardingSeen() async {
    await _storage.setHasSeenOnboarding(true);
    state = state.copyWith(hasSeenOnboarding: true);
  }

  Future<void> signInWithFyda(String fyda, {String? fullName}) async {
    final trimmedFyda = fyda.trim();
    final trimmedName = fullName?.trim();

    await _storage.setFydaNumber(trimmedFyda);
    if (trimmedName != null) {
      await _storage.setFullName(trimmedName);
    }
    await _storage.setHasSeenOnboarding(true);

    final nextFullName = (trimmedName == null)
        ? state.fullName
        : (trimmedName.isEmpty ? null : trimmedName);

    state = AuthState(
      fydaNumber: trimmedFyda.isEmpty ? null : trimmedFyda,
      fullName: nextFullName,
      hasSeenOnboarding: true,
    );
  }
  Future<void> signOutBackend() async {
    await _storage.clearAuthToken();
    await signOut(); // existing signOut clears fyda & fullName and updates state
  }

  Future<void> deleteAccountBackend({
    required ApiClient apiClient,
  }) async {
    await apiClient.delete(ApiEndpoints.authDelete);
    await signOutBackend();
  }


  /// Sign in via backend for registered users only.
  /// Throws ApiException on failure.
  Future<void> signInWithBackend({
    required String identifier,
    required String password,
    required ApiClient apiClient,
  }) async {
    final idTrim = identifier.trim();

    // call backend
    final response = await apiClient.post('/api/v1/auth/login', {
      'identifier': idTrim,
      'password': password,
    });

    final token = response['token'] as String?;
    final user = response['user'] as Map<String, dynamic>?;

    if (token == null || user == null) {
      throw Exception('Invalid response from server (missing token/user).');
    }

    // persist token & profile in storage
    await _storage.setAuthToken(token);

    final nationalId = (user['national_id'] ?? user['nationalId'] ?? idTrim)
        .toString();
    await _storage.setFydaNumber(nationalId);

    final first =
        (user['first_name'] ?? user['firstName'] ?? '')?.toString() ?? '';
    final last =
        (user['last_name'] ?? user['lastName'] ?? '')?.toString() ?? '';
    final display = (('$first $last').trim());
    if (display.isNotEmpty) await _storage.setFullName(display);

    await _storage.setHasSeenOnboarding(true);

    // update in-memory state using storage values
    state = AuthState(
      fydaNumber: _storage.fydaNumber,
      fullName: _storage.fullName,
      hasSeenOnboarding: true,
    );
  }

  Future<void> verifyFydaWithBackend({
    required String nationalId,
    required ApiClient apiClient,
    String? dateOfBirth,
    String? phone,
    String? email,
    String? password,
    String? passwordConfirmation,
  }) async {
    final payload = <String, dynamic>{
      'national_id': nationalId.trim(),
    };
    if (dateOfBirth != null && dateOfBirth.trim().isNotEmpty) {
      payload['date_of_birth'] = dateOfBirth.trim();
    }
    if (phone != null && phone.trim().isNotEmpty) {
      payload['phone'] = phone.trim();
    }
    if (email != null && email.trim().isNotEmpty) {
      payload['email'] = email.trim();
    }
    if (password != null && password.trim().isNotEmpty) {
      payload['password'] = password;
      payload['password_confirmation'] = passwordConfirmation ?? '';
    }

    final response = await apiClient.post('/api/v1/auth/verify-fyda', payload);
    final token = response['token'] as String?;
    final user = response['user'] as Map<String, dynamic>?;

    if (token == null || user == null) {
      throw Exception('Invalid response from server (missing token/user).');
    }

    await _storage.setAuthToken(token);

    final nationalIdValue =
        (user['national_id'] ?? user['nationalId'] ?? nationalId).toString();
    await _storage.setFydaNumber(nationalIdValue);

    final first =
        (user['first_name'] ?? user['firstName'] ?? '')?.toString() ?? '';
    final last =
        (user['last_name'] ?? user['lastName'] ?? '')?.toString() ?? '';
    final display = (('$first $last').trim());
    if (display.isNotEmpty) await _storage.setFullName(display);

    await _storage.setHasSeenOnboarding(true);

    state = AuthState(
      fydaNumber: _storage.fydaNumber,
      fullName: _storage.fullName,
      hasSeenOnboarding: true,
    );
  }

  Future<void> createAccount({
    required String fydaNumber,
    required String fullName,
  }) {
    return signInWithFyda(fydaNumber, fullName: fullName);
  }

  Future<void> updateProfile({String? fydaNumber, String? fullName}) async {
    final trimmedFyda = fydaNumber?.trim();
    final trimmedName = fullName?.trim();

    if (trimmedFyda != null) {
      await _storage.setFydaNumber(trimmedFyda);
    }
    if (trimmedName != null) {
      await _storage.setFullName(trimmedName);
    }

    final nextFyda = (trimmedFyda == null)
        ? state.fydaNumber
        : (trimmedFyda.isEmpty ? null : trimmedFyda);

    final nextFullName = (trimmedName == null)
        ? state.fullName
        : (trimmedName.isEmpty ? null : trimmedName);

    state = AuthState(
      fydaNumber: nextFyda,
      fullName: nextFullName,
      hasSeenOnboarding: state.hasSeenOnboarding,
    );
  }

  Future<void> signOut() async {
    await Future.wait([
      _storage.clearAuthToken(),
      _storage.clearFydaNumber(),
      _storage.clearFullName(),
    ]);

    state = AuthState(
      fydaNumber: null,
      fullName: null,
      hasSeenOnboarding: state.hasSeenOnboarding,
    );
  }
}

final authControllerProvider = StateNotifierProvider<AuthController, AuthState>(
  (ref) {
    final storage = ref.watch(appStorageProvider);
    return AuthController(storage);
  },
);
