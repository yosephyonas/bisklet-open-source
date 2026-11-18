// lib/ui/auth/signin_page.dart
import 'package:bisklet/core/network/network_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:bisklet/core/i18n/app_i18n.dart';
import 'package:bisklet/core/providers/app_providers.dart';
import 'package:bisklet/core/network/api_client.dart';
import 'package:bisklet/core/theme/app_theme.dart';
import 'package:bisklet/core/utils/haptics.dart';

import '../application/auth_controller.dart';

class SignInPage extends ConsumerStatefulWidget {
  const SignInPage({super.key});

  @override
  ConsumerState<SignInPage> createState() => _SignInPageState();
}

class _SignInPageState extends ConsumerState<SignInPage> {
  final _fydaController = TextEditingController();
  final _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _obscurePassword = true;
  bool _isSubmitting = false;

  @override
  void dispose() {
    _fydaController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleSubmitBackend() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    Haptics.medium();
    setState(() => _isSubmitting = true);

    final identifier = _fydaController.text.trim();
    debugPrint('[signin] starting sign-in for identifier=$identifier');

    try {
      // get the ApiClient provider (constructed from storage token + base url)
      final apiClient = ref.read(apiClientProvider);
      debugPrint('[signin] apiClient.baseUrl=${apiClient.baseUrl}');
      debugPrint(
        '[signin] apiClient.authToken (before)=${apiClient.authToken}',
      );

      // call the StateNotifier method that does the API call & writes token to storage
      await ref
          .read(authControllerProvider.notifier)
          .signInWithBackend(
            identifier: identifier,
            password: _passwordController.text,
            apiClient: apiClient,
          );

      // Rebuild ApiClient provider so it picks up the saved token in AppStorage
      ref.invalidate(apiClientProvider);

      // read storage to show saved values (helpful to ensure persistence worked)
      final storage = ref.read(appStorageProvider);
      debugPrint('[signin] storage.authToken=${storage.authToken}');
      debugPrint('[signin] storage.fydaNumber=${storage.fydaNumber}');
      debugPrint('[signin] storage.fullName=${storage.fullName}');

      if (!mounted) return;

      debugPrint('[signin] login succeeded, returning to app');
      Haptics.light();
      Navigator.of(context).pushNamedAndRemoveUntil('/home', (route) => false);
    } catch (e, st) {
      if (e is ApiException) {
        debugPrint(
          '[signin][error] ApiException: ${e.message} status=${e.statusCode}',
        );
        if (e.payload != null) {
          debugPrint('[signin][error] payload=${e.payload}');
        }
      } else {
        debugPrint('[signin][error] $e');
      }
      debugPrint('[signin][error] stacktrace: $st');

      final message = (e is ApiException) ? e.message : e.toString();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${context.l10n.tr('auth.signin_failed')}: $message'),
          ),
        );
        Haptics.selection();
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final l10n = context.l10n;
    final isDark = theme.brightness == Brightness.dark;

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              colorScheme.surface,
              colorScheme.primary.withValues(alpha: isDark ? 0.18 : 0.12),
              colorScheme.secondary.withValues(alpha: isDark ? 0.14 : 0.10),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        SizedBox(
                          height: 44,
                          width: 44,
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                              gradient: const LinearGradient(
                                colors: AppTheme.heroGradient,
                              ),
                              shape: BoxShape.circle,
                              boxShadow: [
                                BoxShadow(
                                  color: colorScheme.primary.withValues(
                                    alpha: 0.25,
                                  ),
                                  blurRadius: 14,
                                  offset: const Offset(0, 6),
                                ),
                              ],
                            ),
                            child: const Icon(
                              Icons.pedal_bike,
                              color: Colors.white,
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Bisklet',
                                style: theme.textTheme.headlineSmall?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  letterSpacing: 0.6,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Container(
                                height: 2,
                                width: 28,
                                decoration: BoxDecoration(
                                  color: colorScheme.primary,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                              ),
                              Text(
                                l10n.tr('auth.brand_subtitle_signin'),
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.textTheme.bodySmall?.color
                                      ?.withValues(alpha: 0.7),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(22),
                      decoration: BoxDecoration(
                        color: colorScheme.surface.withValues(alpha: 0.94),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: colorScheme.outlineVariant.withValues(
                            alpha: 0.6,
                          ),
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.06),
                            blurRadius: 18,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: Theme(
                        data: theme.copyWith(
                          inputDecorationTheme: theme.inputDecorationTheme
                              .copyWith(
                                filled: false,
                                isDense: false,
                                contentPadding: const EdgeInsets.symmetric(
                                  horizontal: 0,
                                  vertical: 10,
                                ),
                                prefixIconColor: colorScheme.onSurfaceVariant,
                                border: UnderlineInputBorder(
                                  borderSide: BorderSide(
                                    color: colorScheme.outlineVariant,
                                  ),
                                ),
                                enabledBorder: UnderlineInputBorder(
                                  borderSide: BorderSide(
                                    color: colorScheme.outlineVariant,
                                  ),
                                ),
                                focusedBorder: UnderlineInputBorder(
                                  borderSide: BorderSide(
                                    color: colorScheme.primary,
                                    width: 1.4,
                                  ),
                                ),
                              ),
                        ),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                                l10n.tr('auth.signin'),
                                style: theme.textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Container(
                                height: 2,
                                width: 36,
                                decoration: BoxDecoration(
                                  color: colorScheme.primary,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _fydaController,
                                keyboardType: TextInputType.number,
                                decoration: InputDecoration(
                                  labelText: l10n.tr('auth.fyda'),
                                  prefixIcon: const Icon(Icons.badge_outlined),
                                ),
                                onTap: Haptics.selection,
                                validator: (value) {
                                  if (value == null || value.trim().isEmpty) {
                                    return l10n.tr('auth.please_enter_fyda');
                                  }
                                  if (value.trim().length < 6) {
                                    return l10n.tr('auth.fyda_len');
                                  }
                                  return null;
                                },
                                textInputAction: TextInputAction.next,
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _passwordController,
                                obscureText: _obscurePassword,
                                decoration: InputDecoration(
                                  labelText: l10n.tr('auth.password'),
                                  prefixIcon: const Icon(Icons.lock_outlined),
                                  suffixIcon: IconButton(
                                    onPressed: () {
                                      Haptics.selection();
                                      setState(
                                        () => _obscurePassword =
                                            !_obscurePassword,
                                      );
                                    },
                                    icon: Icon(
                                      _obscurePassword
                                          ? Icons.visibility_outlined
                                          : Icons.visibility_off_outlined,
                                    ),
                                  ),
                                ),
                                onTap: Haptics.selection,
                                validator: (value) {
                                  if (value == null || value.isEmpty) {
                                    return l10n.tr(
                                      'auth.please_enter_password',
                                    );
                                  }
                                  if (value.length < 6) {
                                    return l10n.tr('auth.password_len');
                                  }
                                  return null;
                                },
                                onFieldSubmitted: (_) => _handleSubmitBackend(),
                              ),
                              const SizedBox(height: 24),
                              FilledButton(
                                onPressed: _isSubmitting
                                    ? null
                                    : _handleSubmitBackend,
                                style: FilledButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(
                                    vertical: 12,
                                    horizontal: 16,
                                  ),
                                ),
                                child: _isSubmitting
                                    ? const SizedBox(
                                        height: 20,
                                        width: 20,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : Text(
                                        l10n.tr('auth.signin'),
                                        style: theme.textTheme.labelLarge
                                            ?.copyWith(
                                              fontWeight: FontWeight.w600,
                                            ),
                                      ),
                              ),
                              const SizedBox(height: 8),
                              TextButton(
                                onPressed: _isSubmitting
                                    ? null
                                    : () {
                                        Haptics.selection();
                                        Navigator.of(
                                          context,
                                        ).pushReplacementNamed('/signup');
                                      },
                                style: TextButton.styleFrom(
                                  foregroundColor: AppColors.primaryBlue,
                                ),
                                child: Text(l10n.tr('auth.create_account')),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
