import 'package:flutter/material.dart';

import '../../../core/widgets/page_header.dart';

class SafetyGuidelinesPage extends StatelessWidget {
  const SafetyGuidelinesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ListView(
        padding: const EdgeInsets.fromLTRB(0, 8, 0, 32),
        children: [
          const PageHeader(
            title: 'Safety guidelines',
            icon: Icons.health_and_safety_outlined,
          ),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                _GuidelineCard(
                  icon: Icons.health_and_safety_outlined,
                  title: 'Wear a helmet',
                  description:
                      'Protect your head and make sure the strap is tightened.',
                ),
                _GuidelineCard(
                  icon: Icons.remove_red_eye_outlined,
                  title: 'Stay visible',
                  description:
                      'Use lights at night and wear bright clothing during the day.',
                ),
                _GuidelineCard(
                  icon: Icons.directions,
                  title: 'Follow traffic rules',
                  description:
                      'Ride with traffic, obey signals, and use bike lanes when available.',
                ),
                _GuidelineCard(
                  icon: Icons.nature_people_outlined,
                  title: 'Respect pedestrians',
                  description:
                      'Slow down in crowded areas and give pedestrians the right of way.',
                ),
                _GuidelineCard(
                  icon: Icons.lock_outline,
                  title: 'Lock responsibly',
                  description:
                      'Park in a safe spot and end your ride to avoid extra charges.',
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GuidelineCard extends StatelessWidget {
  const _GuidelineCard({
    required this.icon,
    required this.title,
    required this.description,
  });

  final IconData icon;
  final String title;
  final String description;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: theme.colorScheme.primary.withValues(alpha: 0.08),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 44,
            width: 44,
            decoration: BoxDecoration(
              color: theme.colorScheme.primary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: theme.colorScheme.primary),
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
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  description,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.textTheme.bodySmall?.color?.withValues(
                      alpha: 0.7,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
