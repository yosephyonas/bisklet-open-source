import "package:flutter/material.dart";
import "package:flutter_riverpod/flutter_riverpod.dart";

import '../../auth/application/auth_controller.dart';
import "../../../core/utils/haptics.dart";
import '../../../core/theme/theme_mode_controller.dart';
import '../../../core/theme/locale_controller.dart';
import '../../../core/i18n/app_i18n.dart';
import '../../../core/widgets/page_header.dart';
import '../../payments/application/payment_methods_controller.dart';
import '../../../core/network/network_providers.dart';
import '../../../core/network/api_client.dart';

class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final authState = ref.watch(authControllerProvider);
    final themeMode = ref.watch(themeModeProvider);
    final appLocale = ref.watch(appLocaleControllerProvider);
    final paymentMethods = ref.watch(paymentMethodsControllerProvider);
    final fyda = authState.fydaNumber ?? 'Not linked yet';
    final displayName = authState.displayName;
    final initials = _initialsFor(displayName);
    final defaultPayment = paymentMethods.isEmpty
        ? null
        : paymentMethods.firstWhere(
            (method) => method.isDefault,
            orElse: () => paymentMethods.first,
          );
    final paymentSubtitle = defaultPayment == null
        ? context.l10n.tr('profile.add_payment')
        : '${defaultPayment.label} ${defaultPayment.detail}';
    Future<void> handleEditProfile() async {
      Haptics.selection();
      final updated = await _showEditProfileDialog(
        context,
        ref,
        authState,
      );
      if (!context.mounted || !updated) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.tr('profile.updated')),
        ),
      );
    }

    return SafeArea(
      child: ScrollConfiguration(
        behavior: const _NoGlowScrollBehavior(),
        child: ListView(
          physics: const ClampingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
          children: [
            _ProfileHeroCard(
              displayName: displayName,
              fyda: fyda,
              initials: initials,
              onEdit: handleEditProfile,
            ),
            const SizedBox(height: 32),
            _ProfileSection(
              title: context.l10n.tr('profile.account'),
              subtitle: context.l10n.tr('profile.account_subtitle'),
              children: [
                _ProfileTile(
                  icon: Icons.credit_card,
                  title: context.l10n.tr('profile.payment_methods'),
                  subtitle: paymentSubtitle,
                  onTap: () => Navigator.of(context).pushNamed('/payments'),
                ),
                _ProfileTile(
                  icon: Icons.electric_bike,
                  title: context.l10n.tr('profile.ride_history'),
                  subtitle: context.l10n.tr('profile.ride_history_subtitle'),
                  onTap: () => Navigator.of(context).pushNamed('/ride-history'),
                ),
                _ProfileTile(
                  icon: Icons.notifications_none,
                  title: context.l10n.tr('profile.notifications'),
                  subtitle: context.l10n.tr('profile.notifications_subtitle'),
                  onTap: () =>
                      Navigator.of(context).pushNamed('/notifications'),
                ),
              ],
            ),
            _ProfileSection(
              title: context.l10n.tr('profile.support'),
              subtitle: context.l10n.tr('profile.support_subtitle'),
              children: [
                _ProfileTile(
                  icon: Icons.support_agent_outlined,
                  title: context.l10n.tr('profile.help_center'),
                  subtitle: context.l10n.tr('profile.help_center_subtitle'),
                  onTap: () => Navigator.of(context).pushNamed('/support'),
                ),
                _ProfileTile(
                  icon: Icons.security_outlined,
                  title: context.l10n.tr('profile.safety_guidelines'),
                  subtitle: context.l10n.tr('profile.safety_guidelines_subtitle'),
                  onTap: () =>
                      Navigator.of(context).pushNamed('/safety-guidelines'),
                ),
              ],
            ),
            _ProfileSection(
              title: context.l10n.tr('profile.app'),
              subtitle: context.l10n.tr('profile.app_subtitle'),
              children: [
                _ProfileTile(
                  icon: Icons.language,
                  title: context.l10n.tr('profile.language'),
                  subtitle: _localeLabel(context, appLocale),
                  subtitleColor: colorScheme.primary,
                  onTap: () => _showLanguageSheet(context, ref, appLocale),
                ),
                _ProfileTile(
                  icon: Icons.color_lens_outlined,
                  title: context.l10n.tr('profile.theme'),
                  subtitle: context.l10n.tr(_themeModeLabel(themeMode)),
                  subtitleColor: colorScheme.primary,
                  onTap: () => _showThemeModeSheet(context, ref, themeMode),
                ),
                _ProfileTile(
                  icon: Icons.logout,
                  title: context.l10n.tr('profile.signout'),
                  subtitle: context.l10n.tr('profile.signout_subtitle'),
                  onTap: () async {
                    Haptics.heavy();
                    await ref.read(authControllerProvider.notifier).signOut();
                    if (context.mounted) {
                      Navigator.of(
                        context,
                      ).pushNamedAndRemoveUntil('/signin', (_) => false);
                    }
                  },
                ),
                _ProfileTile(
                  icon: Icons.delete_forever,
                  title: context.l10n.tr('profile.delete'),
                  subtitle: context.l10n.tr('profile.delete_subtitle'),
                  subtitleColor: Colors.red,
                  onTap: () async {
                    Haptics.heavy();
                    final confirmed = await _confirmDeleteAccount(context);
                    if (!confirmed) return;
                    try {
                      final apiClient = ref.read(apiClientProvider);
                      await ref
                          .read(authControllerProvider.notifier)
                          .deleteAccountBackend(apiClient: apiClient);
                      if (!context.mounted) return;
                      Navigator.of(
                        context,
                      ).pushNamedAndRemoveUntil('/signin', (_) => false);
                    } catch (e) {
                      if (!context.mounted) return;
                      final message = e is ApiException
                          ? e.message
                          : e.toString();
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text(
                            '${context.l10n.tr('profile.delete_failed')}: $message',
                          ),
                        ),
                      );
                    }
                  },
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileHeroCard extends StatelessWidget {
  const _ProfileHeroCard({
    required this.displayName,
    required this.fyda,
    required this.initials,
    required this.onEdit,
  });

  final String displayName;
  final String fyda;
  final String initials;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            colorScheme.primary.withValues(alpha: 0.92),
            colorScheme.primaryContainer.withValues(alpha: 0.96),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: colorScheme.primary.withValues(alpha: 0.22),
            blurRadius: 26,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              CircleAvatar(
                radius: 34,
                backgroundColor: Colors.white.withValues(alpha: 0.2),
                child: Text(
                  initials,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  displayName,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                    height: 1.05,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              IconButton(
                onPressed: onEdit,
                icon: const Icon(Icons.edit_outlined),
                style: IconButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: colorScheme.primary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            fyda == 'Not linked yet' ? fyda : 'FYDA $fyda',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.titleMedium?.copyWith(
              color: Colors.white.withValues(alpha: 0.86),
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

class _NoGlowScrollBehavior extends ScrollBehavior {
  const _NoGlowScrollBehavior();

  @override
  Widget buildOverscrollIndicator(
    BuildContext context,
    Widget child,
    ScrollableDetails details,
  ) {
    return child;
  }
}

class _ProfileSection extends StatelessWidget {
  const _ProfileSection({
    required this.title,
    required this.subtitle,
    required this.children,
  });

  final String title;
  final String subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(height: 16),
          ...children,
          Container(
            height: 1,
            margin: const EdgeInsets.only(top: 20),
            color: colorScheme.primary.withValues(alpha: 0.05),
          ),
        ],
      ),
    );
  }
}

class _ProfileTile extends StatelessWidget {
  const _ProfileTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.subtitleColor,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final Color? subtitleColor;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final isDark = theme.brightness == Brightness.dark;
    final iconBackground = isDark
        ? colorScheme.surfaceVariant.withValues(alpha: 0.6)
        : null;
    final iconGradient = isDark
        ? null
        : LinearGradient(
            colors: [
              colorScheme.primary.withValues(alpha: 0.18),
              colorScheme.primaryContainer.withValues(alpha: 0.32),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          );
    final iconColor = isDark ? colorScheme.onSurface : colorScheme.primary;
    final chevronColor = isDark
        ? colorScheme.onSurfaceVariant
        : colorScheme.primary;
    return InkWell(
      onTap: () {
        Haptics.selection();
        onTap();
      },
      borderRadius: BorderRadius.circular(22),
      child: Ink(
        decoration: BoxDecoration(
          color: colorScheme.surface,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(
            color: colorScheme.primary.withValues(alpha: 0.06),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        child: Row(
          children: [
            Container(
              height: 52,
              width: 52,
              decoration: BoxDecoration(
                color: iconBackground,
                gradient: iconGradient,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(icon, color: iconColor),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color:
                          subtitleColor ??
                          theme.textTheme.bodySmall?.color?.withValues(
                            alpha: 0.7,
                          ),
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: chevronColor),
          ],
        ),
      ),
    );
  }
}

String _themeModeLabel(ThemeMode mode) {
  switch (mode) {
    case ThemeMode.light:
      return 'theme.light';
    case ThemeMode.dark:
      return 'theme.dark';
    case ThemeMode.system:
      return 'theme.system';
  }
}

String _localeLabel(BuildContext context, Locale? locale) {
  final l10n = context.l10n;
  final code = locale?.languageCode;
  if (code == null || code.isEmpty) return l10n.tr('locale.system');
  switch (code) {
    case 'am':
      return l10n.tr('locale.am');
    case 'om':
      return l10n.tr('locale.om');
    case 'ti':
      return l10n.tr('locale.ti');
    default:
      return l10n.tr('locale.en');
  }
}

Future<void> _showLanguageSheet(
  BuildContext context,
  WidgetRef ref,
  Locale? current,
) {
  final l10n = context.l10n;
  final currentCode = current?.languageCode;
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (sheetContext) {
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              PageHeader(
                title: l10n.tr('profile.language'),
                icon: Icons.language,
                onBack: () => Navigator.of(sheetContext).pop(),
                padding: EdgeInsets.zero,
              ),
              const SizedBox(height: 8),
              _LocaleOptionTile(
                label: l10n.tr('locale.system'),
                selected: currentCode == null || currentCode.isEmpty,
                onTap: () async {
                  await ref
                      .read(appLocaleControllerProvider.notifier)
                      .setLocaleCode(null);
                  if (sheetContext.mounted) Navigator.of(sheetContext).pop();
                },
              ),
              _LocaleOptionTile(
                label: l10n.tr('locale.en'),
                selected: currentCode == 'en',
                onTap: () async {
                  await ref
                      .read(appLocaleControllerProvider.notifier)
                      .setLocaleCode('en');
                  if (sheetContext.mounted) Navigator.of(sheetContext).pop();
                },
              ),
              _LocaleOptionTile(
                label: l10n.tr('locale.am'),
                selected: currentCode == 'am',
                onTap: () async {
                  await ref
                      .read(appLocaleControllerProvider.notifier)
                      .setLocaleCode('am');
                  if (sheetContext.mounted) Navigator.of(sheetContext).pop();
                },
              ),
              _LocaleOptionTile(
                label: l10n.tr('locale.om'),
                selected: currentCode == 'om',
                onTap: () async {
                  await ref
                      .read(appLocaleControllerProvider.notifier)
                      .setLocaleCode('om');
                  if (sheetContext.mounted) Navigator.of(sheetContext).pop();
                },
              ),
              _LocaleOptionTile(
                label: l10n.tr('locale.ti'),
                selected: currentCode == 'ti',
                onTap: () async {
                  await ref
                      .read(appLocaleControllerProvider.notifier)
                      .setLocaleCode('ti');
                  if (sheetContext.mounted) Navigator.of(sheetContext).pop();
                },
              ),
            ],
          ),
        ),
      );
    },
  );
}

Future<void> _showThemeModeSheet(
  BuildContext context,
  WidgetRef ref,
  ThemeMode current,
) {
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (context) {
      final theme = Theme.of(context);
      final colorScheme = theme.colorScheme;

      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              PageHeader(
                title: context.l10n.tr('profile.theme'),
                icon: Icons.color_lens_outlined,
                onBack: () => Navigator.of(context).pop(),
                padding: EdgeInsets.zero,
              ),
              const SizedBox(height: 8),
              Text(
                context.l10n.tr('profile.theme_desc'),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.textTheme.bodySmall?.color?.withValues(
                    alpha: 0.7,
                  ),
                ),
              ),
              const SizedBox(height: 12),
              _ThemeModeOption(
                label: context.l10n.tr('theme.system'),
                subtitle: context.l10n.tr('theme.system_desc'),
                value: ThemeMode.system,
                groupValue: current,
                onChanged: (mode) async {
                  await ref.read(themeModeProvider.notifier).setThemeMode(mode);
                  if (context.mounted) Navigator.of(context).pop();
                },
                colorScheme: colorScheme,
              ),
              _ThemeModeOption(
                label: context.l10n.tr('theme.light'),
                subtitle: context.l10n.tr('theme.light_desc'),
                value: ThemeMode.light,
                groupValue: current,
                onChanged: (mode) async {
                  await ref.read(themeModeProvider.notifier).setThemeMode(mode);
                  if (context.mounted) Navigator.of(context).pop();
                },
                colorScheme: colorScheme,
              ),
              _ThemeModeOption(
                label: context.l10n.tr('theme.dark'),
                subtitle: context.l10n.tr('theme.dark_desc'),
                value: ThemeMode.dark,
                groupValue: current,
                onChanged: (mode) async {
                  await ref.read(themeModeProvider.notifier).setThemeMode(mode);
                  if (context.mounted) Navigator.of(context).pop();
                },
                colorScheme: colorScheme,
              ),
            ],
          ),
        ),
      );
    },
  );
}

class _ThemeModeOption extends StatelessWidget {
  const _ThemeModeOption({
    required this.label,
    required this.subtitle,
    required this.value,
    required this.groupValue,
    required this.onChanged,
    required this.colorScheme,
  });

  final String label;
  final String subtitle;
  final ThemeMode value;
  final ThemeMode groupValue;
  final ValueChanged<ThemeMode> onChanged;
  final ColorScheme colorScheme;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final selected = value == groupValue;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: selected
              ? colorScheme.primary
              : colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
        color: selected
            ? colorScheme.primary.withValues(alpha: 0.08)
            : colorScheme.surface,
      ),
      child: RadioListTile<ThemeMode>(
        value: value,
        groupValue: groupValue,
        onChanged: (mode) {
          if (mode != null) onChanged(mode);
        },
        title: Text(
          label,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w600,
          ),
        ),
        subtitle: Text(
          subtitle,
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
          ),
        ),
        activeColor: colorScheme.primary,
      ),
    );
  }
}

class _LocaleOptionTile extends StatelessWidget {
  const _LocaleOptionTile({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return ListTile(
      onTap: onTap,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      leading: Icon(
        selected ? Icons.radio_button_checked : Icons.radio_button_off,
        color: selected ? colorScheme.primary : colorScheme.outline,
      ),
      title: Text(label),
    );
  }
}

String _initialsFor(String name) {
  final parts = name.trim().split(' ');
  if (parts.length == 1) {
    return parts.first.isEmpty
        ? 'B'
        : parts.first.characters.first.toUpperCase();
  }
  final first = parts.first.isEmpty ? '' : parts.first.characters.first;
  final last = parts.last.isEmpty ? '' : parts.last.characters.first;
  return (first + last).toUpperCase();
}

Future<bool> _showEditProfileDialog(
  BuildContext context,
  WidgetRef ref,
  AuthState authState,
) async {
  final controller = TextEditingController(text: authState.displayName);
  final fydaController = TextEditingController(text: authState.fydaNumber);
  final result = await showDialog<bool>(
    context: context,
    builder: (dialogContext) {
      return AlertDialog(
        title: Text(context.l10n.tr('profile.edit_title')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: controller,
              decoration: InputDecoration(
                labelText: context.l10n.tr('profile.full_name'),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: fydaController,
              decoration: InputDecoration(
                labelText: context.l10n.tr('auth.fyda'),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.l10n.tr('common.cancel')),
          ),
          FilledButton(
            onPressed: () async {
              await ref
                  .read(authControllerProvider.notifier)
                  .updateProfile(
                    fullName: controller.text,
                    fydaNumber: fydaController.text,
                  );
              if (dialogContext.mounted) {
                Navigator.of(dialogContext).pop(true);
              }
            },
            child: Text(context.l10n.tr('common.save')),
          ),
        ],
      );
    },
  );
  return result ?? false;
}

Future<bool> _confirmDeleteAccount(BuildContext context) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (dialogContext) {
      return AlertDialog(
        title: Text(context.l10n.tr('profile.delete_confirm_title')),
        content: Text(context.l10n.tr('profile.delete_confirm_body')),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.l10n.tr('common.cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(context.l10n.tr('profile.delete')),
          ),
        ],
      );
    },
  );
  return result ?? false;
}
