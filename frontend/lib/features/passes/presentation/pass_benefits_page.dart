import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

class PassBenefitsPage extends StatelessWidget {
  const PassBenefitsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Commuter Unlimited')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(28),
              gradient: LinearGradient(
                colors: AppTheme.heroGradient,
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Unlimited 30-minute rides',
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Perfect for daily commuters. Ride as often as you like and only pay overtime fees when you go beyond 30 minutes.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: Colors.white70,
                  ),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () => _showSubscriptionSheet(context),
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: Colors.black,
                  ),
                  child: const Text('Manage subscription'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Text(
            'What you get',
            style: theme.textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          ..._benefits.map((benefit) => _BenefitTile(benefit: benefit)),
          const SizedBox(height: 24),
          Text(
            'Usage insights',
            style: theme.textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 18,
                  offset: const Offset(0, 12),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                _InsightRow(
                  label: 'Rides taken with pass',
                  value: '18 this month',
                ),
                SizedBox(height: 12),
                _InsightRow(label: 'Overtime fees avoided', value: 'ETB 126'),
                SizedBox(height: 12),
                _InsightRow(
                  label: 'Average ride duration',
                  value: '17 minutes',
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Text(
            'FAQs',
            style: theme.textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          ..._faqs.map((faq) => _FaqTile(faq: faq)),
        ],
      ),
    );
  }
}

Future<void> _showSubscriptionSheet(BuildContext context) async {
  await showModalBottomSheet<void>(
    context: context,
    backgroundColor: Theme.of(context).colorScheme.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
    ),
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);
      return Padding(
        padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: theme.dividerColor,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Subscription options',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 12),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.swap_horiz),
              title: const Text('Change plan'),
              subtitle: const Text('Explore other ride passes'),
              onTap: () {
                Navigator.of(sheetContext).pop();
                Navigator.of(context).pushNamed('/payments');
              },
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.pause_circle_outline),
              title: const Text('Pause subscription'),
              subtitle: const Text('Pause and resume whenever you want'),
              onTap: () {
                Navigator.of(sheetContext).pop();
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Subscription pause request sent.'),
                  ),
                );
              },
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.cancel_outlined),
              title: const Text('Cancel subscription'),
              subtitle: const Text('You can rejoin anytime'),
              onTap: () {
                Navigator.of(sheetContext).pop();
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Subscription cancellation requested.'),
                  ),
                );
              },
            ),
          ],
        ),
      );
    },
  );
}

class _Benefit {
  const _Benefit({
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  final String title;
  final String subtitle;
  final IconData icon;
}

class _BenefitTile extends StatelessWidget {
  const _BenefitTile({required this.benefit});

  final _Benefit benefit;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colorScheme.primary.withValues(alpha: 0.08)),
      ),
      child: ListTile(
        leading: Container(
          height: 44,
          width: 44,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            color: colorScheme.secondary.withValues(alpha: 0.15),
          ),
          child: Icon(benefit.icon, color: colorScheme.secondary),
        ),
        title: Text(
          benefit.title,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w600,
          ),
        ),
        subtitle: Text(
          benefit.subtitle,
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
          ),
        ),
      ),
    );
  }
}

class _InsightRow extends StatelessWidget {
  const _InsightRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
            ),
          ),
        ),
        Text(
          value,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _Faq {
  const _Faq({required this.question, required this.answer});

  final String question;
  final String answer;
}

class _FaqTile extends StatelessWidget {
  const _FaqTile({required this.faq});

  final _Faq faq;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            faq.question,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            faq.answer,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
            ),
          ),
        ],
      ),
    );
  }
}

const _benefits = [
  _Benefit(
    title: 'Unlimited unlocks',
    subtitle: 'Start as many 30-minute rides as you need each day.',
    icon: Icons.timer_outlined,
  ),
  _Benefit(
    title: 'Priority support',
    subtitle: 'Skip the line when reaching out to our rider success team.',
    icon: Icons.support_agent_outlined,
  ),
  _Benefit(
    title: 'Partner perks',
    subtitle: 'Discounts on helmets and gear from local partners.',
    icon: Icons.emoji_events_outlined,
  ),
];

const _faqs = [
  _Faq(
    question: 'How do overtime fees work?',
    answer:
        'If your ride exceeds 30 minutes, a small overtime fee is charged every 15 minutes.',
  ),
  _Faq(
    question: 'Can I pause the subscription?',
    answer:
        'Yes, you can pause once per calendar quarter straight from the app settings.',
  ),
];
