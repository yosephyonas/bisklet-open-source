import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/haptics.dart';

import '../application/ride_api_providers.dart';
import '../application/bike_tracking_providers.dart';
import '../application/ride_metrics_controller.dart';
import '../application/ride_status_providers.dart';
import '../application/ride_session_controller.dart';
import '../domain/ride_metrics.dart';
import '../services/bike_tracking_service.dart';

class ActiveRidePage extends ConsumerWidget {
  const ActiveRidePage({super.key});
  static const double _fareEstimateBirrPerKm = 15.0;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final metrics = ref.watch(rideMetricsProvider);
    final clockTick = ref.watch(rideClockProvider).maybeWhen(
      data: (value) => value,
      orElse: DateTime.now,
    );
    final session = ref.watch(rideSessionControllerProvider);
    final sessionNotifier = ref.read(rideSessionControllerProvider.notifier);
    final messenger = ScaffoldMessenger.of(context);
    final colorScheme = Theme.of(context).colorScheme;
    final rideFlow = ref.read(rideFlowServiceProvider);
    final bikeLocations = ref.watch(bikeLocationsStreamProvider);
    final rideSync = ref.watch(rideStatusStreamProvider).maybeWhen(
      data: (value) => value,
      orElse: () => null,
    );
    final activeRide = rideSync?.activeRide;
    final backendEstimatedTotal = activeRide?.estimatedTotalAmount;
    final isPaused = session.isPaused;
    final now =
        isPaused && session.pausedAt != null ? session.pausedAt! : clockTick;
    final elapsedSeconds = session.rideStartedAt == null
        ? (metrics.durationMinutes * 60).round()
        : now.difference(session.rideStartedAt!).inSeconds;
    final elapsedMinutes = elapsedSeconds / 60.0;
    final durationLabel = elapsedMinutes.toStringAsFixed(0);
    final perMinuteRate = session.perMinuteRate;
    final unlockFee = session.unlockFee ?? 0.0;

    Future<void> startRideFromLockCode(String lockCode) async {
      final cleaned = lockCode.trim();
      if (cleaned.isEmpty) return;
      try {
        final bikes = bikeLocations.value ?? const <BikeLocation>[];
        BikeLocation? matchedBike;
        for (final bike in bikes) {
          if (bike.id.toLowerCase() == cleaned.toLowerCase()) {
            matchedBike = bike;
            break;
          }
        }
        final fallbackRate = matchedBike?.pricePerMinute;
        final result = await rideFlow.startRideFromLockCode(cleaned);
        if (!result.isSuccess) {
          messenger.showSnackBar(
            SnackBar(
              content: Text(
                'Unlock failed (${result.unlock.status}). Try again.',
              ),
            ),
          );
          return;
        }
        await sessionNotifier.reserveBike(
          cleaned,
          lockImei: result.scan.imei,
        );
        await sessionNotifier.startRide(startedAt: result.activeRide?.startedAt);
        final perMinuteRate =
            result.activeRide?.perMinuteRate ?? fallbackRate;
        final unlockFee = result.activeRide?.unlockFee;
        await sessionNotifier.attachRideInfo(
          rideId: result.activeRide?.id,
          lockImei: result.scan.imei,
          perMinuteRate: perMinuteRate,
          unlockFee: unlockFee,
        );
        Haptics.medium();
        messenger.showSnackBar(
          const SnackBar(content: Text('Ride started. Have a safe trip!')),
        );
      } on ApiException catch (e) {
        messenger.showSnackBar(
          SnackBar(content: Text('Unlock failed: ${e.message}')),
        );
      } catch (e) {
        messenger.showSnackBar(
          SnackBar(content: Text('Unlock failed: $e')),
        );
      }
    }

    Future<void> handleStartRide() async {
      Haptics.medium();
      final bikeId = session.bikeId;
      if (bikeId == null || bikeId.trim().isEmpty) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Scan a bike to start your ride.')),
        );
        return;
      }
      await startRideFromLockCode(bikeId);
    }

    Future<void> handlePauseRide() async {
      await sessionNotifier.pauseRide();
      messenger.showSnackBar(
        const SnackBar(content: Text('Ride paused.')),
      );
    }

    Future<void> handleResumeRide() async {
      await sessionNotifier.resumeRide();
      messenger.showSnackBar(
        const SnackBar(content: Text('Ride resumed.')),
      );
    }

    Future<void> handleCancelReservation() async {
      await sessionNotifier.cancelReservation();
      messenger.showSnackBar(
        const SnackBar(content: Text('Reservation cancelled')),
      );
    }

    Future<void> handleScanBike() async {
      Haptics.selection();
      final result = await Navigator.of(context).pushNamed('/scanner');
      if (!context.mounted) return;
      final bikeId = result is String ? result.trim() : '';
      if (bikeId.isEmpty) return;
      await startRideFromLockCode(bikeId);
    }

    final showSummary =
        session.isIdle && metrics.distanceKm > 0 && !metrics.isLive;

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 32, 20, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    height: 34,
                    width: 34,
                    decoration: BoxDecoration(
                      color: colorScheme.primary.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      Icons.pedal_bike,
                      color: colorScheme.primary,
                      size: 18,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Text(
                    'Bisklet',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              _RideStatusCard(
                session: session,
                metrics: metrics,
                colorScheme: colorScheme,
                durationLabel: durationLabel,
                onStartRide: handleStartRide,
                onPauseRide: handlePauseRide,
                onResumeRide: handleResumeRide,
                onCancelReservation: handleCancelReservation,
                onScan: () {
                  handleScanBike();
                },
              ),
              const SizedBox(height: 18),
              _LiveMetricsStrip(
                metrics: metrics,
                isActive: session.isRiding,
                durationLabel: durationLabel,
                perMinuteRate: perMinuteRate,
                unlockFee: unlockFee,
                elapsedMinutes: elapsedMinutes,
                backendEstimatedTotal: backendEstimatedTotal,
              ),
              if (showSummary) ...[
                const SizedBox(height: 20),
                _RideSummaryCard(
                  metrics: metrics,
                  birrPerKm: _fareEstimateBirrPerKm,
                  perMinuteRate: perMinuteRate,
                  unlockFee: unlockFee,
                ),
                const SizedBox(height: 16),
                _RideSummaryActions(
                  metrics: metrics,
                  birrPerKm: _fareEstimateBirrPerKm,
                  perMinuteRate: perMinuteRate,
                  unlockFee: unlockFee,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

double _estimateFare({
  required double durationMinutes,
  required double distanceKm,
  required double birrPerKmFallback,
  double? perMinuteRate,
  double unlockFee = 0.0,
}) {
  if (perMinuteRate != null && perMinuteRate > 0) {
    final safeMinutes = durationMinutes < 0 ? 0.0 : durationMinutes;
    return unlockFee + (safeMinutes * perMinuteRate);
  }
  return distanceKm * birrPerKmFallback;
}

class _RideSummaryActions extends StatelessWidget {
  const _RideSummaryActions({
    required this.metrics,
    required this.birrPerKm,
    required this.perMinuteRate,
    required this.unlockFee,
  });

  final RideMetrics metrics;
  final double birrPerKm;
  final double? perMinuteRate;
  final double unlockFee;

  @override
  Widget build(BuildContext context) {
    final messenger = ScaffoldMessenger.of(context);
    final distance = metrics.distanceKm.toStringAsFixed(1);
    final duration = metrics.durationMinutes.toStringAsFixed(0);
    final fare = _estimateFare(
      durationMinutes: metrics.durationMinutes,
      distanceKm: metrics.distanceKm,
      perMinuteRate: perMinuteRate,
      unlockFee: unlockFee,
      birrPerKmFallback: birrPerKm,
    ).toStringAsFixed(1);
    return Row(
      children: [
        Expanded(
          child: OutlinedButton.icon(
            onPressed: () {
              final summary =
                  'Bisklet ride summary: $distance km in $duration min, '
                  'estimated cost ETB $fare.';
              Clipboard.setData(ClipboardData(text: summary));
              messenger.showSnackBar(
                const SnackBar(content: Text('Ride summary copied.')),
              );
            },
            icon: const Icon(Icons.share_outlined),
            label: const Text('Share'),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: FilledButton.icon(
            onPressed: () {
              Navigator.of(context).pushNamed('/ride-history');
            },
            icon: const Icon(Icons.history),
            label: const Text('Ride history'),
          ),
        ),
      ],
    );
  }
}

class _RideStatusCard extends StatelessWidget {
  const _RideStatusCard({
    required this.session,
    required this.metrics,
    required this.colorScheme,
    required this.durationLabel,
    required this.onStartRide,
    required this.onPauseRide,
    required this.onResumeRide,
    required this.onCancelReservation,
    required this.onScan,
  });

  final RideSessionState session;
  final RideMetrics metrics;
  final ColorScheme colorScheme;
  final String durationLabel;
  final Future<void> Function() onStartRide;
  final Future<void> Function() onPauseRide;
  final Future<void> Function() onResumeRide;
  final Future<void> Function() onCancelReservation;
  final VoidCallback onScan;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final reserved = session.hasReservation;
    final paused = session.isPaused;
    final active = session.isRiding;
    final accent = paused
        ? Colors.orangeAccent
        : active
            ? colorScheme.primary
            : reserved
                ? Colors.orangeAccent
                : colorScheme.secondary;
    final startedLabel = session.rideStartedAt == null
        ? null
        : TimeOfDay.fromDateTime(session.rideStartedAt!).format(context);

    final statusLabel = paused
        ? 'Paused'
        : active
            ? 'In ride'
            : reserved
                ? 'Reserved'
                : 'Idle';
    final subtitle = paused
        ? 'Ride paused - resume when ready'
        : active
            ? 'Riding for $durationLabel min'
            : reserved
                ? 'Hold expires in ${_formatCountdown(session.reservationTimeLeft)}'
                : 'Scan a bike to start your ride';
    final bikeLabel = session.bikeId ?? metrics.bikeId;

    final infoTiles = <Widget>[
      _InfoChip(
        icon: Icons.battery_full,
        label: 'Battery',
        value: '${metrics.batteryLevel}%',
        accent: colorScheme.secondary,
      ),
      _InfoChip(
        icon: Icons.speed,
        label: active ? 'Speed' : 'Distance',
        value: active
            ? '${metrics.speedKmh.toStringAsFixed(1)} km/h'
            : '${metrics.distanceKm.toStringAsFixed(1)} km',
        accent: colorScheme.primary,
      ),
      if (startedLabel != null)
        _InfoChip(
          icon: Icons.schedule,
          label: 'Started',
          value: startedLabel,
          accent: colorScheme.primary,
        ),
      if (reserved && session.reservationTimeLeft != null)
        _InfoChip(
          icon: Icons.hourglass_bottom,
          label: 'Hold left',
          value: _formatCountdown(session.reservationTimeLeft),
          accent: Colors.orangeAccent,
        ),
    ];

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(
          color: colorScheme.outlineVariant.withOpacity(0.5),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.08),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      bikeLabel.toUpperCase(),
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color:
                            theme.textTheme.bodyMedium?.color?.withOpacity(0.7),
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: accent.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Text(
                  statusLabel,
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: accent,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _InfoGrid(children: infoTiles),
          const SizedBox(height: 16),
          _StatusActions(
            reserved: reserved,
            active: active,
            paused: paused,
            onStartRide: onStartRide,
            onPauseRide: onPauseRide,
            onResumeRide: onResumeRide,
            onCancelReservation: onCancelReservation,
            onScan: onScan,
          ),
        ],
      ),
    );
  }
}

class _StatusActions extends StatelessWidget {
  const _StatusActions({
    required this.reserved,
    required this.active,
    required this.paused,
    required this.onStartRide,
    required this.onPauseRide,
    required this.onResumeRide,
    required this.onCancelReservation,
    required this.onScan,
  });

  final bool reserved;
  final bool active;
  final bool paused;
  final Future<void> Function() onStartRide;
  final Future<void> Function() onPauseRide;
  final Future<void> Function() onResumeRide;
  final Future<void> Function() onCancelReservation;
  final VoidCallback onScan;

  @override
  Widget build(BuildContext context) {
    if (active) {
      return Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => paused ? onResumeRide() : onPauseRide(),
              icon: Icon(paused ? Icons.play_arrow : Icons.pause),
              label: Text(paused ? 'Resume' : 'Pause'),
            ),
          ),
        ],
      );
    }

    if (reserved) {
      return Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: () => onCancelReservation(),
              child: const Text('Cancel'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: FilledButton(
              onPressed: () => onStartRide(),
              child: const Text('Start ride'),
            ),
          ),
        ],
      );
    }

    return FilledButton.icon(
      onPressed: onScan,
      icon: const Icon(Icons.qr_code_scanner),
      label: const Text('Scan to ride'),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({
    required this.icon,
    required this.label,
    required this.value,
    this.accent,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tone = accent ?? theme.colorScheme.primary;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: tone.withOpacity(0.25),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: tone),
          const SizedBox(height: 10),
          Text(
            value,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              color: tone,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: tone.withOpacity(0.75),
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoGrid extends StatelessWidget {
  const _InfoGrid({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    for (var i = 0; i < children.length; i += 2) {
      final rowChildren = <Widget>[Expanded(child: children[i])];
      if (i + 1 < children.length) {
        rowChildren.add(const SizedBox(width: 12));
        rowChildren.add(Expanded(child: children[i + 1]));
      } else {
        rowChildren.add(const Expanded(child: SizedBox.shrink()));
      }
      rows.add(Row(children: rowChildren));
      if (i + 2 < children.length) {
        rows.add(const SizedBox(height: 12));
      }
    }

    return Column(
      children: rows,
    );
  }
}

class _LiveMetricsStrip extends StatelessWidget {
  const _LiveMetricsStrip({
    required this.metrics,
    required this.isActive,
    required this.durationLabel,
    required this.perMinuteRate,
    required this.unlockFee,
    required this.elapsedMinutes,
    required this.backendEstimatedTotal,
  });

  final RideMetrics metrics;
  final bool isActive;
  final String durationLabel;
  final double? perMinuteRate;
  final double unlockFee;
  final double elapsedMinutes;
  final double? backendEstimatedTotal;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final hasData = isActive || metrics.isLive || metrics.distanceKm > 0;
    final showFare = isActive && ((perMinuteRate != null && perMinuteRate! > 0) ||
        (backendEstimatedTotal != null && backendEstimatedTotal! > 0));
    final fareEstimate = showFare
        ? (backendEstimatedTotal ??
            (unlockFee + (elapsedMinutes * (perMinuteRate ?? 0))))
        : 0.0;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withOpacity(0.45),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 20,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: hasData
          ? Row(
              children: [
                _MetricChip(
                  label: 'Time',
                  value: '$durationLabel min',
                  icon: Icons.timer_outlined,
                ),
                const SizedBox(width: 12),
                _MetricChip(
                  label: showFare ? 'Fare' : 'Distance',
                  value: showFare
                      ? 'ETB ${fareEstimate.toStringAsFixed(1)}'
                      : '${metrics.distanceKm.toStringAsFixed(1)} km',
                  icon: showFare ? Icons.payments_outlined : Icons.route_outlined,
                ),
                const SizedBox(width: 12),
                _MetricChip(
                  label: 'Speed',
                  value: '${metrics.speedKmh.toStringAsFixed(1)} km/h',
                  icon: Icons.speed,
                ),
              ],
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Live stats will appear once your ride starts.',
                  style: theme.textTheme.bodyMedium,
                ),
                const SizedBox(height: 6),
                Text(
                  'Track time, distance, and speed in real time.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.textTheme.bodySmall?.color?.withOpacity(0.7),
                  ),
                ),
              ],
            ),
    );
  }
}

class _MetricChip extends StatelessWidget {
  const _MetricChip({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: theme.colorScheme.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: theme.colorScheme.outlineVariant.withOpacity(0.4),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 18, color: theme.colorScheme.primary),
            const SizedBox(height: 8),
            Text(
              value,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.textTheme.bodySmall?.color?.withOpacity(0.7),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RideSummaryCard extends StatelessWidget {
  const _RideSummaryCard({
    required this.metrics,
    required this.birrPerKm,
    required this.perMinuteRate,
    required this.unlockFee,
  });

  final RideMetrics metrics;
  final double birrPerKm;
  final double? perMinuteRate;
  final double unlockFee;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final fareEstimate = _estimateFare(
      durationMinutes: metrics.durationMinutes,
      distanceKm: metrics.distanceKm,
      perMinuteRate: perMinuteRate,
      unlockFee: unlockFee,
      birrPerKmFallback: birrPerKm,
    );

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: theme.colorScheme.primary.withOpacity(0.08),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                'Ride summary',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const Spacer(),
              Chip(
                label: Text(
                  '${metrics.co2SavedKg.toStringAsFixed(2)} kg CO₂ saved',
                ),
                avatar: const Icon(Icons.eco, size: 18),
                side: BorderSide.none,
                backgroundColor:
                    theme.colorScheme.secondary.withOpacity(0.12),
                materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              _SummaryEntry(
                label: 'Distance',
                value: '${metrics.distanceKm.toStringAsFixed(2)} km',
              ),
              _SummaryEntry(
                label: 'Duration',
                value: '${metrics.durationMinutes.toStringAsFixed(0)} min',
              ),
              _SummaryEntry(
                label: 'Avg. speed',
                value: '${metrics.speedKmh.toStringAsFixed(1)} km/h',
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: theme.colorScheme.primary.withOpacity(0.06),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: [
                Icon(Icons.receipt_long, color: theme.colorScheme.primary),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Estimated fare',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.textTheme.bodySmall?.color
                              ?.withOpacity(0.7),
                        ),
                      ),
                      Text(
                        '${fareEstimate.toStringAsFixed(1)} Birr',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  perMinuteRate != null && perMinuteRate! > 0
                      ? 'Based on ${perMinuteRate!.toStringAsFixed(1)} Birr/min'
                      : 'Based on ${birrPerKm.toStringAsFixed(0)} Birr/km',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color:
                        theme.textTheme.bodySmall?.color?.withOpacity(0.6),
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

class _SummaryEntry extends StatelessWidget {
  const _SummaryEntry({
    required this.label,
    required this.value,
  });

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.textTheme.bodySmall?.color?.withOpacity(0.7),
            ),
          ),
        ],
      ),
    );
  }
}

String _formatCountdown(Duration? duration) {
  if (duration == null) return '--';
  final minutes = duration.inMinutes;
  final seconds = duration.inSeconds.remainder(60);
  if (minutes > 0) {
    return '${minutes}m ${seconds.toString().padLeft(2, '0')}s';
  }
  return '${seconds}s';
}
