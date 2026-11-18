import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/widgets/page_header.dart';

const _supportEmail = 'support@bisklet.app';
const _supportPhone = '+251 900 000 000';

class SupportPage extends StatelessWidget {
  const SupportPage({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Scaffold(
      body: ListView(
        padding: const EdgeInsets.fromLTRB(0, 8, 0, 32),
        children: [
          const PageHeader(
            title: 'Help center',
            icon: Icons.help_outline,
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(22),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(28),
                    color: colorScheme.primaryContainer,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.08),
                        blurRadius: 20,
                        offset: const Offset(0, 12),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: colorScheme.onPrimaryContainer
                              .withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          'Support',
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: colorScheme.onPrimaryContainer,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 0.2,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        'We are here to help',
                        style: theme.textTheme.headlineSmall?.copyWith(
                          color: colorScheme.onPrimaryContainer,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        'Browse quick guides or reach our support team for anything related to your rides.',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: colorScheme.onPrimaryContainer
                              .withValues(alpha: 0.75),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          Expanded(
                            child: FilledButton.tonalIcon(
                              onPressed: () => _showSupportChatSheet(context),
                              icon: const Icon(Icons.chat_bubble_outline),
                              label: const Text('Chat'),
                              style: FilledButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 14,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: FilledButton.icon(
                              onPressed: () => _showSupportCallSheet(context),
                              icon: const Icon(Icons.phone),
                              label: const Text('Call'),
                              style: FilledButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 14,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                Text(
                  'Quick topics',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                ..._supportTopics.map((topic) => _SupportTile(topic: topic)),
                const SizedBox(height: 24),
                Text(
                  'Recent updates',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                ..._newsItems.map((news) => _NewsCard(item: news)),
                const SizedBox(height: 24),
                Text(
                  'Still need help?',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(20),
                    color: colorScheme.surface,
                    border: Border.all(
                      color: colorScheme.outlineVariant.withValues(alpha: 0.5),
                    ),
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
                    children: [
                      Row(
                        children: [
                          Container(
                            height: 40,
                            width: 40,
                            decoration: BoxDecoration(
                              color: colorScheme.primary.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Icon(
                              Icons.mail_outline,
                              color: colorScheme.primary,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              _supportEmail,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                          TextButton(
                            onPressed: () {
                              Clipboard.setData(
                                const ClipboardData(text: _supportEmail),
                              );
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Email copied.')),
                              );
                            },
                            child: const Text('Copy'),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Text(
                        'Available 7 days a week from 8 AM to 10 PM.',
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
          ),
        ],
      ),
    );
  }
}

Future<void> _showSupportChatSheet(BuildContext context) async {
  final controller = TextEditingController();
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Theme.of(context).colorScheme.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
    ),
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);
      return Padding(
        padding: EdgeInsets.only(
          left: 24,
          right: 24,
          top: 20,
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 24,
        ),
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
              'Message support',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              maxLines: 4,
              decoration: const InputDecoration(
                hintText: 'Describe the issue you need help with',
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () {
                  final text = controller.text.trim();
                  if (text.isEmpty) {
                    ScaffoldMessenger.of(sheetContext).showSnackBar(
                      const SnackBar(content: Text('Enter a message first.')),
                    );
                    return;
                  }
                  Navigator.of(sheetContext).pop();
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Support message sent.')),
                  );
                },
                child: const Text('Send message'),
              ),
            ),
          ],
        ),
      );
    },
  );
  controller.dispose();
}

Future<void> _showSupportCallSheet(BuildContext context) async {
  await showDialog<void>(
    context: context,
    builder: (dialogContext) {
      return AlertDialog(
        title: const Text('Call support'),
        content: Text('Reach us at $_supportPhone'),
        actions: [
          TextButton(
            onPressed: () {
              Clipboard.setData(const ClipboardData(text: _supportPhone));
              Navigator.of(dialogContext).pop();
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Phone number copied.')),
              );
            },
            child: const Text('Copy number'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Close'),
          ),
        ],
      );
    },
  );
}

Future<void> _showSupportTopic(
  BuildContext context,
  _SupportTopic topic,
) async {
  await showDialog<void>(
    context: context,
    builder: (dialogContext) {
      return AlertDialog(
        title: Text(topic.title),
        content: Text(topic.subtitle),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Got it'),
          ),
        ],
      );
    },
  );
}

Future<void> _showNewsDetails(BuildContext context, _NewsItem item) async {
  await showDialog<void>(
    context: context,
    builder: (dialogContext) {
      return AlertDialog(
        title: Text(item.title),
        content: Text(item.description),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Close'),
          ),
        ],
      );
    },
  );
}

class _SupportTopic {
  const _SupportTopic({
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  final String title;
  final String subtitle;
  final IconData icon;
}

class _SupportTile extends StatelessWidget {
  const _SupportTile({required this.topic});

  final _SupportTopic topic;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ListTile(
        onTap: () => _showSupportTopic(context, topic),
        leading: Container(
          height: 44,
          width: 44,
          decoration: BoxDecoration(
            color: colorScheme.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(topic.icon, color: colorScheme.primary),
        ),
        title: Text(
          topic.title,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w600,
          ),
        ),
        subtitle: Text(
          topic.subtitle,
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
          ),
        ),
        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 18),
      ),
    );
  }
}

class _NewsItem {
  const _NewsItem({
    required this.title,
    required this.description,
    required this.tag,
  });

  final String title;
  final String description;
  final String tag;
}

class _NewsCard extends StatelessWidget {
  const _NewsCard({required this.item});

  final _NewsItem item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: colorScheme.primary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              item.tag,
              style: theme.textTheme.labelSmall?.copyWith(
                color: colorScheme.primary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            item.title,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            item.description,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.75),
            ),
          ),
          const SizedBox(height: 12),
          TextButton(
            onPressed: () => _showNewsDetails(context, item),
            child: const Text('Read more'),
          ),
        ],
      ),
    );
  }
}

const _supportTopics = [
  _SupportTopic(
    title: 'Unlocking bikes',
    subtitle: 'Steps to scan a QR code and start your ride.',
    icon: Icons.qr_code_scanner,
  ),
  _SupportTopic(
    title: 'Ending a ride',
    subtitle: 'How to park responsibly and lock the bike.',
    icon: Icons.lock_open_outlined,
  ),
  _SupportTopic(
    title: 'Billing and refunds',
    subtitle: 'Understand charges, passes, and credits.',
    icon: Icons.receipt_long_outlined,
  ),
];

const _newsItems = [
  _NewsItem(
    title: 'New smart docks in Bole',
    description:
        'We just launched 6 new docking hubs around Bole with fast charging.',
    tag: 'Product update',
  ),
  _NewsItem(
    title: 'Ride safe at night',
    description:
        'Check out our safety checklist for keeping visible after dark.',
    tag: 'Safety',
  ),
];
