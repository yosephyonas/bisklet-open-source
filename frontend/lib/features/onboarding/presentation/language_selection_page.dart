import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/locale_controller.dart';

class LanguageSelectionPage extends ConsumerWidget {
  const LanguageSelectionPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final isDark = theme.brightness == Brightness.dark;

    Future<void> selectLanguage(String code) async {
      await ref.read(appLocaleControllerProvider.notifier).setLocaleCode(code);
    }

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              colorScheme.surface,
              colorScheme.primary.withValues(alpha: isDark ? 0.18 : 0.10),
              colorScheme.secondary.withValues(alpha: isDark ? 0.14 : 0.08),
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Align(
                  alignment: Alignment.centerLeft,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: const LinearGradient(
                        colors: AppTheme.heroGradient,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: colorScheme.primary.withValues(alpha: 0.25),
                          blurRadius: 18,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: const SizedBox(
                      width: 56,
                      height: 56,
                      child: Icon(
                        Icons.language_rounded,
                        color: Colors.white,
                        size: 28,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  'Choose your language',
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Afaan filadhu',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 24),
                _LanguageTile(
                  label: 'English',
                  subtitle: 'English',
                  onTap: () => selectLanguage('en'),
                ),
                _LanguageTile(
                  label: '\u12A0\u121B\u122D\u129B',
                  subtitle: 'Amharic',
                  onTap: () => selectLanguage('am'),
                ),
                _LanguageTile(
                  label: 'Afaan Oromo',
                  subtitle: 'Oromo',
                  onTap: () => selectLanguage('om'),
                ),
                _LanguageTile(
                  label: '\u1275\u130D\u122D\u129B',
                  subtitle: 'Tigrinya',
                  onTap: () => selectLanguage('ti'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _LanguageTile extends StatelessWidget {
  const _LanguageTile({
    required this.label,
    required this.subtitle,
    required this.onTap,
  });

  final String label;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: colorScheme.surface.withValues(alpha: 0.94),
        borderRadius: BorderRadius.circular(16),
        elevation: 1,
        shadowColor: colorScheme.shadow.withValues(alpha: 0.10),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Ink(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: colorScheme.outlineVariant.withValues(alpha: 0.80),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        label,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: colorScheme.primary),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
