import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:bisklet/core/i18n/app_i18n.dart';
import 'package:bisklet/core/network/api_client.dart';
import 'package:bisklet/core/network/network_providers.dart';
import 'package:bisklet/core/theme/app_theme.dart';
import 'package:bisklet/core/utils/haptics.dart';
import '../application/auth_controller.dart';

class SignUpPage extends ConsumerStatefulWidget {
  const SignUpPage({super.key});

  @override
  ConsumerState<SignUpPage> createState() => _SignUpPageState();
}

class _SignUpPageState extends ConsumerState<SignUpPage> {
  final _formKey = GlobalKey<FormState>();
  final _nationalIdController = TextEditingController();
  final _dobController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _isSubmitting = false;

  @override
  void dispose() {
    _nationalIdController.dispose();
    _dobController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _pickDob() async {
    Haptics.selection();
    final now = DateTime.now();
    final initialDate = DateTime(now.year - 20, now.month, now.day);
    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(1900),
      lastDate: DateTime(now.year - 10, now.month, now.day),
    );
    if (picked == null) return;
    final month = picked.month.toString().padLeft(2, '0');
    final day = picked.day.toString().padLeft(2, '0');
    _dobController.text = '${picked.year}-$month-$day';
  }

  Future<void> _handleSubmit() async {
    final form = _formKey.currentState;
    if (form == null || !form.validate()) {
      return;
    }

    final nationalId = _nationalIdController.text.trim();

    Haptics.medium();
    setState(() {
      _isSubmitting = true;
    });

    try {
      final apiClient = ref.read(apiClientProvider);
      await ref
          .read(authControllerProvider.notifier)
          .verifyFydaWithBackend(
            nationalId: nationalId,
            dateOfBirth: _dobController.text.trim(),
            phone: _phoneController.text.trim(),
            email: _emailController.text.trim(),
            password: _passwordController.text,
            passwordConfirmation: _confirmPasswordController.text,
            apiClient: apiClient,
          );
      ref.invalidate(apiClientProvider);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.tr('auth.welcome'))));
      Haptics.light();
      Navigator.of(context).pushNamedAndRemoveUntil('/home', (route) => false);
    } catch (e) {
      if (!mounted) return;
      final message = e is ApiException ? e.message : e.toString();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${context.l10n.tr('auth.signup_failed')}: $message'),
        ),
      );
      Haptics.selection();
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
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
                constraints: const BoxConstraints(maxWidth: 460),
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
                              Icons.badge_outlined,
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
                                  color: colorScheme.secondary,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                              ),
                              Text(
                                l10n.tr('auth.brand_subtitle_signup'),
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
                                    color: colorScheme.secondary,
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
                                l10n.tr('auth.create_account_title'),
                                style: theme.textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Container(
                                height: 2,
                                width: 36,
                                decoration: BoxDecoration(
                                  color: colorScheme.secondary,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _nationalIdController,
                                keyboardType: TextInputType.number,
                                decoration: const InputDecoration(
                                  labelText: 'FYDA national ID',
                                  prefixIcon: Icon(Icons.badge_outlined),
                                ),
                                onTap: Haptics.selection,
                                validator: (value) {
                                  final trimmed = value?.trim() ?? '';
                                  if (trimmed.isEmpty) {
                                    return 'Enter your FYDA national ID';
                                  }
                                  if (!RegExp(
                                    r'^\\d{12,14}$',
                                  ).hasMatch(trimmed)) {
                                    return 'ID must be 12-14 digits';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _dobController,
                                readOnly: true,
                                onTap: _pickDob,
                                decoration: const InputDecoration(
                                  labelText: 'Date of birth (YYYY-MM-DD)',
                                  prefixIcon: Icon(
                                    Icons.calendar_today_outlined,
                                  ),
                                ),
                                validator: (value) {
                                  final trimmed = value?.trim() ?? '';
                                  if (trimmed.isEmpty) return null;
                                  if (!RegExp(
                                    r'^\\d{4}-\\d{2}-\\d{2}$',
                                  ).hasMatch(trimmed)) {
                                    return 'Use YYYY-MM-DD';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _phoneController,
                                keyboardType: TextInputType.phone,
                                decoration: const InputDecoration(
                                  labelText: 'Phone number (optional)',
                                  prefixIcon: Icon(Icons.phone_outlined),
                                ),
                                onTap: Haptics.selection,
                                validator: (value) {
                                  final trimmed = value?.trim() ?? '';
                                  if (trimmed.isEmpty) return null;
                                  if (trimmed.length < 7) {
                                    return 'Enter a valid phone number';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _emailController,
                                keyboardType: TextInputType.emailAddress,
                                decoration: const InputDecoration(
                                  labelText: 'Email (optional)',
                                  prefixIcon: Icon(Icons.mail_outline),
                                ),
                                onTap: Haptics.selection,
                                validator: (value) {
                                  final trimmed = value?.trim() ?? '';
                                  if (trimmed.isEmpty) return null;
                                  if (!trimmed.contains('@')) {
                                    return 'Enter a valid email';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _passwordController,
                                obscureText: true,
                                decoration: const InputDecoration(
                                  labelText: 'Password',
                                  prefixIcon: Icon(Icons.lock_outline),
                                ),
                                onTap: Haptics.selection,
                                validator: (value) {
                                  final trimmed = value ?? '';
                                  if (trimmed.isEmpty) {
                                    return 'Create a password';
                                  }
                                  if (trimmed.length < 8) {
                                    return 'Use at least 8 characters';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _confirmPasswordController,
                                obscureText: true,
                                decoration: const InputDecoration(
                                  labelText: 'Confirm password',
                                  prefixIcon: Icon(Icons.lock_outline),
                                ),
                                onTap: Haptics.selection,
                                validator: (value) {
                                  if (value == null || value.isEmpty) {
                                    return 'Confirm your password';
                                  }
                                  if (value != _passwordController.text) {
                                    return 'Passwords do not match';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 24),
                              FilledButton(
                                onPressed: _isSubmitting ? null : _handleSubmit,
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
                                        l10n.tr('auth.create_account_title'),
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
                                        ).pushReplacementNamed('/signin');
                                      },
                                style: TextButton.styleFrom(
                                  foregroundColor: AppColors.primaryBlue,
                                ),
                                child: Text(
                                  l10n.tr('auth.already_have_account'),
                                ),
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
