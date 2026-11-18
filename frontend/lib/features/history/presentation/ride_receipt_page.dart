import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/page_header.dart';
import '../application/ride_receipt_providers.dart';
import '../domain/ride_receipt_models.dart';

class RideReceiptPage extends ConsumerWidget {
  const RideReceiptPage({super.key, required this.rideId});

  final String rideId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final receiptAsync = ref.watch(rideReceiptProvider(rideId));
    final theme = Theme.of(context);

    return Scaffold(
      body: receiptAsync.when(
        data: (receipt) => _RideReceiptContent(receipt: receipt),
        error: (error, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Unable to load receipt.',
                  style: theme.textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                Text(
                  error.toString(),
                  style: theme.textTheme.bodySmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: () => ref.invalidate(rideReceiptProvider(rideId)),
                  child: const Text('Retry'),
                ),
              ],
            ),
          ),
        ),
        loading: () => const Center(child: CircularProgressIndicator()),
      ),
    );
  }
}

class _RideReceiptContent extends StatelessWidget {
  const _RideReceiptContent({required this.receipt});

  final RideReceipt receipt;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final currency = receipt.currency ?? 'ETB';
    final total = receipt.totalAmount ?? 0.0;
    final bikeLabel = receipt.bikeCode ?? 'Bike';
    final startLabel = (receipt.startStationName ?? '').isNotEmpty
        ? receipt.startStationName!
        : 'Start point';
    final endLabel = (receipt.endStationName ?? '').isNotEmpty
        ? receipt.endStationName!
        : 'End point';
    final durationLabel = '${receipt.durationMinutes.toStringAsFixed(0)} min';
    final distanceLabel = '${receipt.distanceKm.toStringAsFixed(2)} km';
    final endedAtLabel = _formatReceiptTime(context, receipt.endedAt);

    return ListView(
      padding: const EdgeInsets.fromLTRB(0, 8, 0, 32),
      children: [
        const PageHeader(title: 'Ride receipt', icon: Icons.receipt_long),
        const SizedBox(height: 12),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: colorScheme.surface,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: colorScheme.outlineVariant.withValues(alpha: 0.5),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Total',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color:
                            theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '$currency ${total.toStringAsFixed(2)}',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      endedAtLabel,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color:
                            theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              _ReceiptDetailRow(label: 'Ride ID', value: receipt.id),
              _ReceiptDetailRow(label: 'Bike', value: bikeLabel),
              _ReceiptDetailRow(label: 'Start', value: startLabel),
              _ReceiptDetailRow(label: 'End', value: endLabel),
              _ReceiptDetailRow(label: 'Duration', value: durationLabel),
              _ReceiptDetailRow(label: 'Distance', value: distanceLabel),
              const SizedBox(height: 18),
              Text(
                'Pricing',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              _ReceiptDetailRow(
                label: 'Unlock fee',
                value: _formatMoney(currency, receipt.unlockFee),
              ),
              _ReceiptDetailRow(
                label: 'Per minute',
                value: _formatMoney(currency, receipt.perMinuteRate),
              ),
              _ReceiptDetailRow(
                label: 'Minutes billed',
                value: receipt.minutes?.toString() ?? '--',
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: colorScheme.primary.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Row(
                  children: [
                    Icon(Icons.receipt_long, color: colorScheme.primary),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Total paid',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    Text(
                      '$currency ${total.toStringAsFixed(2)}',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ReceiptDetailRow extends StatelessWidget {
  const _ReceiptDetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color:
                    theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
              ),
            ),
          ),
          Text(
            value,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

String _formatMoney(String currency, double? amount) {
  if (amount == null) return '--';
  return '$currency ${amount.toStringAsFixed(2)}';
}

String _formatReceiptTime(BuildContext context, DateTime? date) {
  if (date == null) return 'Receipt date unavailable';
  final timeLabel = MaterialLocalizations.of(context).formatTimeOfDay(
    TimeOfDay.fromDateTime(date),
  );
  return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')} $timeLabel';
}
