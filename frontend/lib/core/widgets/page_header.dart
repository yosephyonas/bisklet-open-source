import 'package:flutter/material.dart';

class PageHeader extends StatelessWidget {
  const PageHeader({
    super.key,
    required this.title,
    required this.icon,
    this.onBack,
    this.padding = const EdgeInsets.fromLTRB(12, 12, 16, 0),
  });

  final String title;
  final IconData icon;
  final VoidCallback? onBack;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final canPop = Navigator.of(context).canPop();

    return Padding(
      padding: padding,
      child: SafeArea(
        bottom: false,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            if (canPop)
              IconButton(
                onPressed: onBack ?? () => Navigator.of(context).maybePop(),
                icon: const Icon(Icons.arrow_back_ios_new_rounded),
                color: colorScheme.onSurface,
                iconSize: 18,
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
              )
            else
              const SizedBox(width: 32),
            Container(
              height: 34,
              width: 34,
              decoration: BoxDecoration(
                color: colorScheme.primary,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: colorScheme.onPrimary, size: 18),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: colorScheme.onSurface,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
