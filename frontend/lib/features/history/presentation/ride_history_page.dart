import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/page_header.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/network_providers.dart';
import '../application/ride_history_providers.dart';
import '../domain/ride_history_models.dart';
import 'ride_receipt_page.dart';

class RideHistoryPage extends ConsumerWidget {
  const RideHistoryPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final ridesAsync = ref.watch(rideHistoryProvider);
    final apiBaseUrl = ref.watch(apiBaseUrlProvider);

    return Scaffold(
      body: ridesAsync.when(
        data: (rides) {
          final summary = _buildMonthlySummary(rides);
          final entries = _groupHistoryEntries(
            rides,
            DateTime.now(),
            context,
          );

          return ListView(
            padding: const EdgeInsets.fromLTRB(0, 8, 0, 32),
            children: [
              const PageHeader(
                title: 'Ride history',
                icon: Icons.history,
              ),
              const SizedBox(height: 12),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
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
                            summary.title,
                            style: theme.textTheme.titleLarge?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Row(
                            children: [
                              _SummaryStat(
                                label: 'Total rides',
                                value: summary.totalRides,
                              ),
                              const SizedBox(width: 16),
                              _SummaryStat(
                                label: 'Distance',
                                value: summary.totalDistance,
                              ),
                              const SizedBox(width: 16),
                              _SummaryStat(
                                label: 'CO2 saved',
                                value: summary.co2Saved,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),
                    if (entries.isEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 32),
                        child: Text(
                          'No completed rides yet.',
                          style: theme.textTheme.bodyLarge,
                          textAlign: TextAlign.center,
                        ),
                      )
                    else
                      ...entries.map(
                        (entry) => Padding(
                          padding: const EdgeInsets.only(bottom: 18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                entry.dateLabel,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: theme
                                      .textTheme.titleMedium?.color
                                      ?.withValues(
                                        alpha: 0.75,
                                      ),
                                ),
                              ),
                              const SizedBox(height: 12),
                              ...entry.rides.map(
                                (ride) => _RideHistoryCard(
                                  ride: ride,
                                  onTap: () {
                                    if (ride.rideIdRaw.trim().isEmpty) return;
                                    Navigator.of(context).push(
                                      MaterialPageRoute(
                                        builder: (_) => RideReceiptPage(
                                          rideId: ride.rideIdRaw,
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          );
        },
        error: (error, _) {
          final isTimeout =
              error is ApiException &&
              error.message.toLowerCase().contains('timed out');
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Unable to load ride history.',
                    style: theme.textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    error.toString(),
                    style: theme.textTheme.bodySmall,
                    textAlign: TextAlign.center,
                  ),
                  if (isTimeout) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Check that the backend is reachable at:',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.textTheme.bodySmall?.color?.withValues(
                          alpha: 0.7,
                        ),
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      apiBaseUrl,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => ref.invalidate(rideHistoryProvider),
                    child: const Text('Retry'),
                  ),
                ],
              ),
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
      ),
    );
  }
}

List<_RideHistoryEntryGroup> _groupHistoryEntries(
  List<RideHistoryRide> rides,
  DateTime now,
  BuildContext context,
) {
  final filtered = rides.where((ride) {
    final date = ride.primaryDate;
    if (date == null) return false;
    return true;
  }).toList()
    ..sort((a, b) {
      final aDate = a.primaryDate ?? DateTime.fromMillisecondsSinceEpoch(0);
      final bDate = b.primaryDate ?? DateTime.fromMillisecondsSinceEpoch(0);
      return bDate.compareTo(aDate);
    });

  final groups = <String, List<_RideEntry>>{};
  for (final ride in filtered) {
    final date = ride.primaryDate ?? now;
    final label = _formatDateLabel(date, now);
    groups.putIfAbsent(label, () => []).add(_mapRideEntry(ride, now, context));
  }

  return groups.entries
      .map((entry) => _RideHistoryEntryGroup(
            dateLabel: entry.key,
            rides: entry.value,
          ))
      .toList();
}

_RideEntry _mapRideEntry(
  RideHistoryRide ride,
  DateTime now,
  BuildContext context,
) {
  final start = ride.startStationName?.trim();
  final end = ride.endStationName?.trim();
  final startLabel =
      (start ?? '').isNotEmpty ? start! : 'Central Plaza';
  final endLabel =
      (end ?? '').isNotEmpty ? end! : 'Harbor View';

  final rideMinutes = ride.minutes != null && ride.minutes! > 0
      ? ride.minutes
      : (ride.durationSeconds != null
            ? (ride.durationSeconds! / 60).ceil()
            : null);
  final durationLabel = rideMinutes != null ? '${rideMinutes} min' : '--';
  final distanceLabel = ride.distanceMeters != null
      ? '${(ride.distanceMeters! / 1000).toStringAsFixed(1)} km'
      : '--';

  final currency = ride.currency?.isNotEmpty == true ? ride.currency : 'ETB';
  final costLabel = ride.totalAmount != null
      ? '$currency ${ride.totalAmount!.toStringAsFixed(2)}'
      : 'Included in pass';
  final rideIdRaw = ride.id.trim();
  final rideId = rideIdRaw.isEmpty ? '---' : rideIdRaw;
  final timeLabel = _formatHistoryTimestamp(
    ride.primaryDate,
    now,
    context,
  );

  return _RideEntry(
    rideId: 'R-$rideId',
    rideIdRaw: rideIdRaw,
    timeLabel: timeLabel,
    startLabel: startLabel,
    endLabel: endLabel,
    duration: durationLabel,
    distance: distanceLabel,
    cost: costLabel,
  );
}

_RideHistorySummary _buildMonthlySummary(List<RideHistoryRide> rides) {
  final now = DateTime.now();
  final monthRides = rides.where((ride) {
    final date = ride.primaryDate;
    return date != null && date.year == now.year && date.month == now.month;
  }).toList();

  final totalDistanceKm = monthRides.fold<double>(
    0,
    (total, ride) =>
        total + ((ride.distanceMeters ?? 0) / 1000),
  );
  final co2SavedKg = totalDistanceKm * 0.12;

  return _RideHistorySummary(
    title: '${_monthName(now.month)} summary',
    totalRides: monthRides.length.toString(),
    totalDistance: '${totalDistanceKm.toStringAsFixed(1)} km',
    co2Saved: '${co2SavedKg.toStringAsFixed(1)} kg',
  );
}

String _formatDateLabel(DateTime date, DateTime now) {
  if (_isSameDay(date, now)) {
    return 'Today - ${_weekdayName(date.weekday)}';
  }
  final yesterday = now.subtract(const Duration(days: 1));
  if (_isSameDay(date, yesterday)) {
    return 'Yesterday - ${_weekdayName(date.weekday)}';
  }
  final lastWeek = now.subtract(const Duration(days: 7));
  if (date.isAfter(lastWeek)) {
    return 'Last week';
  }
  return '${_monthName(date.month)} ${date.day}';
}

String _formatHistoryTimestamp(
  DateTime? date,
  DateTime now,
  BuildContext context,
) {
  if (date == null) {
    return 'Unknown time';
  }
  final timeLabel =
      MaterialLocalizations.of(context).formatTimeOfDay(
            TimeOfDay.fromDateTime(date),
          );
  if (_isSameDay(date, now)) {
    return 'Today, $timeLabel';
  }
  final yesterday = now.subtract(const Duration(days: 1));
  if (_isSameDay(date, yesterday)) {
    return 'Yesterday, $timeLabel';
  }
  final monthLabel = _monthName(date.month).substring(0, 3);
  return '$monthLabel ${date.day}, $timeLabel';
}

bool _isSameDay(DateTime a, DateTime b) {
  return a.year == b.year && a.month == b.month && a.day == b.day;
}

String _monthName(int month) {
  const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];
  if (month < 1 || month > months.length) return 'Unknown';
  return months[month - 1];
}

String _weekdayName(int weekday) {
  const days = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ];
  if (weekday < 1 || weekday > days.length) return '';
  return days[weekday - 1];
}

class _SummaryStat extends StatelessWidget {
  const _SummaryStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                maxLines: 1,
                softWrap: false,
                style: theme.textTheme.titleLarge?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(height: 4),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                label,
                maxLines: 1,
                softWrap: false,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: Colors.white.withValues(alpha: 0.7),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RideHistoryCard extends StatelessWidget {
  const _RideHistoryCard({required this.ride, this.onTap});

  final _RideEntry ride;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: colorScheme.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: colorScheme.outlineVariant.withValues(alpha: 0.5),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 16,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  ride.rideId,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Text(
                ride.cost,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: colorScheme.secondary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            ride.timeLabel,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Column(
                children: [
                  _StopDot(
                    filled: true,
                    color: colorScheme.primary,
                  ),
                  Container(
                    width: 2,
                    height: 18,
                    color: colorScheme.primary.withValues(alpha: 0.3),
                  ),
                  _StopDot(
                    filled: false,
                    color: colorScheme.primary,
                  ),
                ],
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ride.startLabel,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      ride.endLabel,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Divider(color: colorScheme.outlineVariant.withValues(alpha: 0.6)),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(Icons.schedule, size: 16, color: colorScheme.primary),
              const SizedBox(width: 6),
              Text(
                ride.duration,
                style: theme.textTheme.bodySmall?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(width: 18),
              Icon(Icons.route, size: 16, color: colorScheme.primary),
              const SizedBox(width: 6),
              Text(
                ride.distance,
                style: theme.textTheme.bodySmall?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ],
        ),
      ),
    );
  }
}

class _StopDot extends StatelessWidget {
  const _StopDot({required this.filled, required this.color});

  final bool filled;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final surface = Theme.of(context).colorScheme.surface;
    return Container(
      height: 10,
      width: 10,
      decoration: BoxDecoration(
        color: filled ? color : surface,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color, width: 2),
      ),
    );
  }
}

class _RideHistoryEntryGroup {
  const _RideHistoryEntryGroup({required this.dateLabel, required this.rides});

  final String dateLabel;
  final List<_RideEntry> rides;
}

class _RideHistorySummary {
  const _RideHistorySummary({
    required this.title,
    required this.totalRides,
    required this.totalDistance,
    required this.co2Saved,
  });

  final String title;
  final String totalRides;
  final String totalDistance;
  final String co2Saved;
}

class _RideEntry {
  const _RideEntry({
    required this.rideId,
    required this.rideIdRaw,
    required this.timeLabel,
    required this.startLabel,
    required this.endLabel,
    required this.duration,
    required this.distance,
    required this.cost,
  });

  final String rideId;
  final String rideIdRaw;
  final String timeLabel;
  final String startLabel;
  final String endLabel;
  final String duration;
  final String distance;
  final String cost;
}





