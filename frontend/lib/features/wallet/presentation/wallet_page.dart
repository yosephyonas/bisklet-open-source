import 'package:flutter/material.dart';
import 'package:bisklet/core/i18n/app_i18n.dart';

class WalletPage extends StatelessWidget {
  const WalletPage({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final l10n = context.l10n;

    return SafeArea(
      child: Container(
        color: colorScheme.surface,
        child: ListView(
          physics: const ClampingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 32),
          children: [
            const _WalletHeader(),
            const SizedBox(height: 20),
            const _BalanceCard(),
            const SizedBox(height: 26),
            Text(
              l10n.tr('wallet.subscription_plans'),
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
                color: colorScheme.onSurface,
              ),
            ),
            const SizedBox(height: 12),
            _SubscriptionPlanCard(
              title: l10n.tr('wallet.plan_city_title'),
              subtitle: l10n.tr('wallet.plan_city_subtitle'),
              price: l10n.tr('wallet.plan_city_price'),
              accentIcon: Icons.flash_on,
              primary: true,
            ),
            const SizedBox(height: 12),
            _SubscriptionPlanCard(
              title: l10n.tr('wallet.plan_weekend_title'),
              subtitle: l10n.tr('wallet.plan_weekend_subtitle'),
              price: l10n.tr('wallet.plan_weekend_price'),
              accentIcon: Icons.park,
            ),
            const SizedBox(height: 12),
            _SubscriptionPlanCard(
              title: l10n.tr('wallet.plan_night_title'),
              subtitle: l10n.tr('wallet.plan_night_subtitle'),
              price: l10n.tr('wallet.plan_night_price'),
              accentIcon: Icons.nights_stay,
            ),
            const SizedBox(height: 26),
            _TopUpHistorySection(title: l10n.tr('wallet.topup_history')),
            const SizedBox(height: 26),
            const SizedBox(height: 28),
          ],
        ),
      ),
    );
  }
}

class _WalletHeader extends StatelessWidget {
  const _WalletHeader();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Row(
      children: [
        Container(
          height: 48,
          width: 48,
          decoration: BoxDecoration(
            color: colorScheme.primary,
            borderRadius: BorderRadius.circular(16),
          ),
          child: const Icon(
            Icons.account_balance_wallet_outlined,
            color: Colors.white,
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.l10n.tr('nav.wallet'),
                style: theme.textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: colorScheme.onSurface,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Stack(
      children: [
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(28),
            gradient: LinearGradient(
              colors: [colorScheme.primary, colorScheme.primaryContainer],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            boxShadow: [
              BoxShadow(
                color: colorScheme.primary.withValues(alpha: 0.25),
                blurRadius: 32,
                offset: const Offset(0, 18),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    context.l10n.tr('wallet.balance'),
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: Colors.white70,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                'ETB 420.50',
                style: theme.textTheme.displaySmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () =>
                          Navigator.of(context).pushNamed('/payments'),
                      icon: const Icon(Icons.add),
                      style: FilledButton.styleFrom(
                        backgroundColor: colorScheme.secondary,
                        foregroundColor: colorScheme.onSecondary,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      label: Text(context.l10n.tr('wallet.add_funds')),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InsightCard extends StatelessWidget {
  const _InsightCard();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                context.l10n.tr('wallet.this_week'),
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: colorScheme.onSurface,
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: colorScheme.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  context.l10n.tr('wallet.rides_count'),
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colorScheme.onSurface,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _InsightBar(
            label: context.l10n.tr('wallet.ride_costs'),
            amount: 'ETB 112.00',
            fill: 0.65,
            color: null,
          ),
          const SizedBox(height: 10),
          _InsightBar(
            label: context.l10n.tr('wallet.pass_savings'),
            amount: 'ETB 48.00',
            fill: 0.4,
            color: null,
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _InsightStat(
                  label: context.l10n.tr('wallet.distance'),
                  value: '21.8 km',
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _InsightStat(
                  label: context.l10n.tr('wallet.avg_ride'),
                  value: '18 min',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InsightBar extends StatelessWidget {
  const _InsightBar({
    required this.label,
    required this.amount,
    required this.fill,
    this.color,
  });

  final String label;
  final String amount;
  final double fill;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final barColor = color ?? colorScheme.secondary;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.textTheme.bodySmall?.color?.withValues(
                    alpha: 0.7,
                  ),
                ),
              ),
            ),
            Text(
              amount,
              style: theme.textTheme.bodySmall?.copyWith(
                fontWeight: FontWeight.w600,
                color: colorScheme.onSurface,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: Stack(
            children: [
              Container(height: 10, color: colorScheme.surfaceContainerHighest),
              FractionallySizedBox(
                widthFactor: fill.clamp(0, 1),
                child: Container(height: 10, color: barColor),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InsightStat extends StatelessWidget {
  const _InsightStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              color: colorScheme.onSurface,
            ),
          ),
        ],
      ),
    );
  }
}

class _SubscriptionPlanCard extends StatelessWidget {
  const _SubscriptionPlanCard({
    required this.title,
    required this.subtitle,
    required this.price,
    required this.accentIcon,
    this.primary = false,
  });

  final String title;
  final String subtitle;
  final String price;
  final IconData accentIcon;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final background = primary
        ? colorScheme.primary.withValues(alpha: 0.12)
        : colorScheme.surface;
    final border = primary ? colorScheme.primary : colorScheme.outlineVariant;
    final iconColor = primary ? colorScheme.primary : colorScheme.onSurface;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            height: 48,
            width: 48,
            decoration: BoxDecoration(
              color: colorScheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(accentIcon, color: iconColor),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: colorScheme.onSurface,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.textTheme.bodySmall?.color?.withValues(
                      alpha: 0.7,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                price,
                style: theme.textTheme.labelLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: colorScheme.onSurface,
                ),
              ),
              const SizedBox(height: 6),
              FilledButton(
                onPressed: () =>
                    Navigator.of(context).pushNamed('/pass-benefits'),
                style: FilledButton.styleFrom(
                  backgroundColor: colorScheme.primary,
                  foregroundColor: colorScheme.onPrimary,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 6,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: Text(context.l10n.tr('wallet.subscribe')),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TopUpHistorySection extends StatelessWidget {
  const _TopUpHistorySection({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: colorScheme.onSurface,
          ),
        ),
        const SizedBox(height: 12),
        ..._topUpEntries.map(
          (entry) => Container(
            margin: const EdgeInsets.only(bottom: 12),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: colorScheme.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: colorScheme.outlineVariant.withValues(alpha: 0.5),
              ),
            ),
            child: Row(
              children: [
                Container(
                  height: 44,
                  width: 44,
                  decoration: BoxDecoration(
                    color: colorScheme.surfaceContainerHighest,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    Icons.account_balance_wallet_outlined,
                    color: colorScheme.primary,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        entry.method,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: colorScheme.onSurface,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        entry.dateLabel,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.textTheme.bodySmall?.color?.withValues(
                            alpha: 0.7,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  entry.amount,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: colorScheme.secondary,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _TopUpEntry {
  const _TopUpEntry({
    required this.amount,
    required this.method,
    required this.dateLabel,
  });

  final String amount;
  final String method;
  final String dateLabel;
}

const List<_TopUpEntry> _topUpEntries = [
  _TopUpEntry(
    amount: 'ETB 250.00',
    method: 'Chapa top-up',
    dateLabel: 'Feb 1, 2026 · 20:39',
  ),
  _TopUpEntry(
    amount: 'ETB 150.00',
    method: 'Chapa top-up',
    dateLabel: 'Jan 28, 2026 · 18:12',
  ),
  _TopUpEntry(
    amount: 'ETB 500.00',
    method: 'Chapa top-up',
    dateLabel: 'Jan 15, 2026 · 09:05',
  ),
];
