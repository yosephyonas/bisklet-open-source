import 'package:flutter/material.dart';

import '../../../history/presentation/ride_receipt_page.dart';
import '../../domain/ride_metrics.dart';
import '../../domain/ride_summary.dart';

Future<void> showRideCompletionSheet({
  required BuildContext context,
  required RideMetrics metrics,
  required double perMinuteRate,
  RideSummary? summary,
  String? bikeLabelOverride,
}) async {
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (sheetContext) =>
        _RideCompletionSheet(
          metrics: metrics,
          perMinuteRate: perMinuteRate,
          summary: summary,
          bikeLabelOverride: bikeLabelOverride,
        ),
  );
}

class _RideCompletionSheet extends StatelessWidget {
  const _RideCompletionSheet({
    required this.metrics,
    required this.perMinuteRate,
    this.summary,
    this.bikeLabelOverride,
  });

  final RideMetrics metrics;
  final double perMinuteRate;
  final RideSummary? summary;
  final String? bikeLabelOverride;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final summaryDuration = summary?.durationMinutes;
    final duration = (summaryDuration ?? metrics.durationMinutes) < 0
        ? 0.0
        : (summaryDuration ?? metrics.durationMinutes);
    final durationSecondsForDisplay =
        summary?.durationSeconds ?? (duration * 60).round();
    final effectivePerMinuteRate =
        summary?.perMinuteRate ?? perMinuteRate;
    final effectiveUnlockFee = summary?.unlockFee ?? 0.0;
    final fallbackFare = duration <= 0
        ? 0.0
        : (duration * effectivePerMinuteRate) + effectiveUnlockFee;
    final summaryFare = summary?.totalCost;
    final fare = (summaryFare != null && summaryFare > 0)
        ? summaryFare
        : fallbackFare;
    final currency = summary?.currency ?? 'ETB';
    final now = summary?.endedAt ?? DateTime.now();
    final timeLabel = TimeOfDay.fromDateTime(now).format(context);
    final bikeLabel =
        (summary?.bikeLabel ?? bikeLabelOverride ?? metrics.bikeId).toUpperCase();
    final cardBackground = colorScheme.surface;
    final cardSurface = colorScheme.primary.withOpacity(0.08);
    final accent = colorScheme.secondary;
    final onCardText = colorScheme.onSurface;

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          0,
          16,
          MediaQuery.of(context).viewPadding.bottom + 16,
        ),
        child: Container(
          padding: const EdgeInsets.fromLTRB(24, 18, 24, 24),
          decoration: BoxDecoration(
            color: cardBackground,
            borderRadius: BorderRadius.circular(28),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.12),
                blurRadius: 24,
                offset: const Offset(0, 14),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: colorScheme.outlineVariant.withValues(alpha: 0.6),
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: colorScheme.primary.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.pedal_bike, size: 16, color: accent),
                      const SizedBox(width: 8),
                      Text(
                        'Bike $bikeLabel',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: onCardText,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Today, $timeLabel',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: onCardText.withValues(alpha: 0.6),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Ride Summary',
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: onCardText,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.symmetric(vertical: 18),
                decoration: BoxDecoration(
                  color: cardSurface,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: colorScheme.outlineVariant.withValues(alpha: 0.4),
                  ),
                ),
                child: Center(
                  child: Column(
                    children: [
                      Text(
                        'TOTAL COST',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: onCardText.withValues(alpha: 0.6),
                          letterSpacing: 0.6,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '$currency ${fare.toStringAsFixed(2)}',
                        style: theme.textTheme.headlineMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: onCardText,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: _SummaryTile(
                      icon: Icons.timer_outlined,
                      label: 'Total minutes',
                      value: _formatDurationWithSeconds(durationSecondsForDisplay),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _SummaryTile(
                      icon: Icons.av_timer_outlined,
                      label: 'Per minute',
                      value:
                          '$currency ${effectivePerMinuteRate.toStringAsFixed(2)}',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _SummaryTile(
                      icon: Icons.lock_open_rounded,
                      label: 'Unlock fee',
                      value:
                          '$currency ${effectiveUnlockFee.toStringAsFixed(2)}',
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _SummaryTile(
                      icon: Icons.receipt_long_outlined,
                      label: 'Total cost',
                      value: '$currency ${fare.toStringAsFixed(2)}',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Center(
                child: Column(
                  children: [
                    Text(
                      'How was your ride?',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: onCardText.withValues(alpha: 0.9),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: List.generate(5, (index) {
                        final active = index < 4;
                        return Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          child: Icon(
                            Icons.star_rounded,
                            color: active
                                ? accent
                                : onCardText.withValues(alpha: 0.2),
                          ),
                        );
                      }),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              if (summary != null && summary!.id.trim().isNotEmpty) ...[
                OutlinedButton(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => RideReceiptPage(
                          rideId: summary!.id,
                        ),
                      ),
                    );
                  },
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                  ),
                  child: const Text('View receipt'),
                ),
                const SizedBox(height: 12),
              ],
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
                child: const Text('Done'),
              ),
              const SizedBox(height: 10),
              Center(
                child: TextButton.icon(
                  onPressed: () => Navigator.of(context).pushNamed('/support'),
                  icon: const Icon(Icons.flag_outlined, size: 16),
                  label: const Text('Report an issue'),
                  style: TextButton.styleFrom(
                    foregroundColor: onCardText.withValues(alpha: 0.7),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: theme.colorScheme.primary, size: 20),
          const SizedBox(height: 12),
          Text(
            value,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              color: theme.colorScheme.onSurface,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
            ),
          ),
        ],
      ),
    );
  }
}

String _formatDurationWithSeconds(int totalSeconds) {
  final safeSeconds = totalSeconds < 0 ? 0 : totalSeconds;
  final minutes = safeSeconds ~/ 60;
  final seconds = safeSeconds % 60;
  return '${minutes}.${seconds.toString().padLeft(2, '0')}';
}
