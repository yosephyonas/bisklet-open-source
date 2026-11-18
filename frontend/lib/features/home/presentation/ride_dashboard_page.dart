// lib/features/ride/presentation/ride_dashboard_page.dart
import 'dart:io';

import 'package:bisklet/core/providers/app_providers.dart';
import 'package:bisklet/core/network/api_client.dart';
import 'package:bisklet/core/theme/app_theme.dart';
import 'package:bisklet/core/utils/haptics.dart';
import 'package:bisklet/ride_map_page.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/logging/app_logger.dart';
import '../../ride/application/bike_tracking_providers.dart';
import '../../ride/application/ride_api_providers.dart';
import '../../ride/application/ride_metrics_controller.dart';
import '../../ride/application/ride_session_controller.dart';
import '../../ride/application/ride_status_providers.dart';
import '../../ride/services/bike_tracking_service.dart';

class RideDashboardPage extends ConsumerStatefulWidget {
  const RideDashboardPage({super.key});

  @override
  ConsumerState<RideDashboardPage> createState() => _RideDashboardPageState();
}

class _PermissionChip extends StatelessWidget {
  const _PermissionChip({required this.label, required this.enabled});

  final String label;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final background = enabled
        ? colorScheme.secondary.withValues(alpha: 0.18)
        : colorScheme.surface;
    final border = enabled
        ? colorScheme.secondary.withValues(alpha: 0.4)
        : colorScheme.outline.withValues(alpha: 0.3);
    final textColor = enabled ? colorScheme.secondary : colorScheme.onSurface;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: border),
      ),
      child: Text(
        enabled ? '$label enabled' : '$label needed',
        style: theme.textTheme.labelSmall?.copyWith(
          color: textColor,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _RideDashboardPageState extends ConsumerState<RideDashboardPage>
    with WidgetsBindingObserver {
  bool _locationGranted = false;
  bool _notificationGranted = false;
  bool _requestingPermissions = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final storage = ref.read(appStorageProvider);
    _locationGranted = storage.isLocationPermissionGranted;
    _notificationGranted = storage.isNotificationPermissionGranted;
    _syncPermissions();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _syncPermissions();
    }
  }

  Future<void> _syncPermissions() async {
    if (kIsWeb || !(Platform.isAndroid || Platform.isIOS)) return;
    final locationStatus = await Permission.locationWhenInUse.status;
    final notificationStatus = await Permission.notification.status;
    if (!mounted) return;
    final storage = ref.read(appStorageProvider);
    _locationGranted = locationStatus.isGranted;
    _notificationGranted = notificationStatus.isGranted;
    await storage.setLocationPermissionGranted(_locationGranted);
    await storage.setNotificationPermissionGranted(_notificationGranted);
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _requestPermissions() async {
    if (_requestingPermissions) return;
    setState(() => _requestingPermissions = true);
    final locationStatus = await Permission.locationWhenInUse.request();
    final notificationStatus = await Permission.notification.request();
    final storage = ref.read(appStorageProvider);
    _locationGranted = locationStatus.isGranted;
    _notificationGranted = notificationStatus.isGranted;
    await storage.setLocationPermissionGranted(_locationGranted);
    await storage.setNotificationPermissionGranted(_notificationGranted);
    if (mounted) {
      setState(() => _requestingPermissions = false);
    }
  }

  Widget _buildPermissionCard(ThemeData theme) {
    final colorScheme = theme.colorScheme;
    final needsLocation = !_locationGranted;
    final needsNotification = !_notificationGranted;
    if (!needsLocation && !needsNotification) {
      return const SizedBox.shrink();
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colorScheme.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colorScheme.primary.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.lock, color: colorScheme.primary),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Enable location & notifications',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'We use these permissions to show nearby bikes, guide you back to the closest hub, and keep you updated about ride status.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: colorScheme.onSurface.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _PermissionChip(label: 'Location', enabled: !needsLocation),
              const SizedBox(width: 8),
              _PermissionChip(
                label: 'Notifications',
                enabled: !needsNotification,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              TextButton(
                onPressed: _requestingPermissions ? null : _syncPermissions,
                child: const Text('Not now'),
              ),
              const Spacer(),
              FilledButton(
                onPressed: _requestingPermissions ? null : _requestPermissions,
                child: _requestingPermissions
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Allow'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMapPlaceholder(ThemeData theme, double height) {
    final colorScheme = theme.colorScheme;
    return Container(
      height: height,
      decoration: BoxDecoration(
        color: colorScheme.primary.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: colorScheme.primary.withValues(alpha: 0.18)),
      ),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.my_location, color: colorScheme.primary, size: 32),
              const SizedBox(height: 10),
              Text(
                'Turn on location to show the live map.',
                textAlign: TextAlign.center,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'We only use your location to center the map and show nearby bikes.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: colorScheme.onSurface.withValues(alpha: 0.7),
                ),
              ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: _requestingPermissions ? null : _requestPermissions,
                child: const Text('Allow location'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    final metrics = ref.watch(rideMetricsProvider);
    final clockTick = ref
        .watch(rideClockProvider)
        .maybeWhen(data: (value) => value, orElse: DateTime.now);
    final session = ref.watch(rideSessionControllerProvider);
    final sessionNotifier = ref.read(rideSessionControllerProvider.notifier);
    final bikeLocations = ref.watch(bikeLocationsStreamProvider);
    final nearbyBikeCount =
        bikeLocations.asData?.value.where((bike) => !bike.isReserved).length ??
        0;
    final isSearchingNearby = bikeLocations.isLoading;
    final AppLogger logger = ref.watch(appLoggerProvider);
    final messenger = ScaffoldMessenger.of(context);
    final rideFlow = ref.read(rideFlowServiceProvider);

    Future<void> startRideFromLockCode(String lockCode) async {
      final cleaned = lockCode.trim();
      if (cleaned.isEmpty) return;
      if (session.isRiding) {
        messenger.showSnackBar(
          const SnackBar(
            content: Text('End your current ride before starting a new one.'),
          ),
        );
        return;
      }
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
        await sessionNotifier.reserveBike(cleaned, lockImei: result.scan.imei);
        await sessionNotifier.startRide(
          startedAt: result.activeRide?.startedAt,
        );
        final perMinuteRate = result.activeRide?.perMinuteRate ?? fallbackRate;
        final unlockFee = result.activeRide?.unlockFee;
        await sessionNotifier.attachRideInfo(
          rideId: result.activeRide?.id,
          lockImei: result.scan.imei,
          perMinuteRate: perMinuteRate,
          unlockFee: unlockFee,
        );
        if (!context.mounted) return;
        Haptics.medium();
        messenger.showSnackBar(
          const SnackBar(content: Text('Bike unlocked. Ride started!')),
        );
      } on ApiException catch (e) {
        messenger.showSnackBar(
          SnackBar(content: Text('Unlock failed: ${e.message}')),
        );
      } catch (e) {
        messenger.showSnackBar(SnackBar(content: Text('Unlock failed: $e')));
      }
    }

    Future<void> openScanner() async {
      logger.logEvent('open_scanner');
      final result = await Navigator.of(context).pushNamed('/scanner');
      if (!context.mounted) return;
      final bikeId = result is String ? result.trim() : '';
      if (bikeId.isEmpty) return;
      await startRideFromLockCode(bikeId);
    }

    Future<void> openBikeSearch() async {
      final bikes = bikeLocations.value ?? const <BikeLocation>[];
      if (bikes.isEmpty) {
        messenger.showSnackBar(
          const SnackBar(content: Text('No bikes available to search.')),
        );
        return;
      }
      var query = '';
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Theme.of(context).colorScheme.surface,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        builder: (sheetContext) {
          final theme = Theme.of(sheetContext);
          return StatefulBuilder(
            builder: (context, setState) {
              final filtered = bikes
                  .where(
                    (bike) =>
                        bike.id.toLowerCase().contains(query.toLowerCase()),
                  )
                  .toList();
              return Padding(
                padding: EdgeInsets.only(
                  left: 20,
                  right: 20,
                  top: 16,
                  bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 20,
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
                      'Find a bike',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      onChanged: (value) => setState(() => query = value),
                      decoration: const InputDecoration(
                        prefixIcon: Icon(Icons.search),
                        hintText: 'Search by bike ID',
                      ),
                    ),
                    const SizedBox(height: 12),
                    if (filtered.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 20),
                        child: Text(
                          'No bikes match that ID.',
                          style: theme.textTheme.bodyMedium,
                        ),
                      )
                    else
                      Flexible(
                        child: ListView.separated(
                          shrinkWrap: true,
                          itemCount: filtered.length,
                          separatorBuilder: (_, __) =>
                              const Divider(height: 16),
                          itemBuilder: (context, index) {
                            final bike = filtered[index];
                            final batteryLabel = bike.batteryLevel == null
                                ? '--%'
                                : '${bike.batteryLevel}%';
                            return ListTile(
                              contentPadding: EdgeInsets.zero,
                              leading: CircleAvatar(
                                backgroundColor: colorScheme.secondary
                                    .withValues(alpha: 0.15),
                                child: Icon(
                                  Icons.electric_bike,
                                  color: colorScheme.secondary,
                                ),
                              ),
                              title: Text('Bike ${bike.id.toUpperCase()}'),
                              subtitle: Text('Battery $batteryLabel'),
                              trailing: FilledButton.tonal(
                                onPressed: bike.isReserved
                                    ? null
                                    : () async {
                                        await sessionNotifier.reserveBike(
                                          bike.id,
                                        );
                                        if (sheetContext.mounted) {
                                          Navigator.of(sheetContext).pop();
                                        }
                                        messenger.showSnackBar(
                                          SnackBar(
                                            content: Text(
                                              'Bike ${bike.id} reserved for 10 minutes',
                                            ),
                                          ),
                                        );
                                      },
                                child: Text(
                                  bike.isReserved ? 'Reserved' : 'Reserve',
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                  ],
                ),
              );
            },
          );
        },
      );
    }

    void openFullMap() {
      if (!_locationGranted) {
        messenger.showSnackBar(
          const SnackBar(
            content: Text('Enable location to view the live bike map.'),
          ),
        );
        return;
      }
      logger.logEvent('open_map_fullscreen');
      Navigator.of(context).pushNamed('/ride');
    }

    Future<void> handlePrimaryAction() async {
      if (session.isRiding) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Lock the bike to end the ride.')),
        );
        return;
      }
      if (session.hasReservation) {
        openFullMap();
        return;
      }
      final bikes = bikeLocations.value ?? const <BikeLocation>[];
      final BikeLocation? available = bikes.isEmpty
          ? null
          : bikes.firstWhere(
              (bike) => !bike.isReserved,
              orElse: () => bikes.first,
            );
      if (available == null) {
        messenger.showSnackBar(
          const SnackBar(content: Text('No bikes available right now.')),
        );
        return;
      }
      await sessionNotifier.reserveBike(available.id);
      messenger.showSnackBar(
        SnackBar(content: Text('Bike ${available.id} reserved for 10 minutes')),
      );
      logger.logEvent('quick_reserve', parameters: {'bike': available.id});
    }

    Future<void> reserveBike(BikeLocation bike) async {
      await sessionNotifier.reserveBike(bike.id);
      messenger.showSnackBar(
        SnackBar(content: Text('Bike ${bike.id} reserved for 10 minutes')),
      );
      logger.logEvent('reserve_from_list', parameters: {'bike': bike.id});
    }

    void openBikeDetails(BikeLocation bike) {
      ref.read(selectedBikeProvider.notifier).state = bike;
    }

    void refreshMetrics() {
      logger.logEvent('refresh_nearby');
      ref.invalidate(rideMetricsProvider);
    }

    // Idle content — scrollable list so large map doesn't overflow
    Widget buildIdleContent() {
      final screenH = MediaQuery.of(context).size.height;
      final mapHeight = (screenH * 0.55) > 420
          ? 420.0
          : screenH * 0.55; // responsive cap

      return ListView(
        key: const ValueKey('idleRideDashboard'),
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          _buildPermissionCard(theme),
          // ---- Minimal top card ----
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: AppTheme.heroGradient
                    .map((c) => c.withValues(alpha: 0.95))
                    .toList(),
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              children: [
                const Icon(Icons.location_on, color: Colors.white70),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Addis Ababa',
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),

                GestureDetector(
                  onTap: openBikeSearch,
                  child: Container(
                    width: 36,
                    height: 36,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      color: Colors.white24,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.search,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ),

                // primary compact action
                FilledButton.tonal(
                  onPressed: handlePrimaryAction,
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.white.withValues(alpha: 0.12),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    elevation: 0,
                    foregroundColor: Colors.white,
                  ),
                  child: Text(
                    session.isRiding
                        ? 'End'
                        : (session.hasReservation ? 'Open' : 'Reserve'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),

                const SizedBox(width: 8),

                IconButton(
                  onPressed: refreshMetrics,
                  icon: Icon(Icons.refresh_rounded, color: Colors.white),
                  tooltip: 'Refresh',
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          // ---- Map preview (larger) ----
          _locationGranted
              ? GestureDetector(
                  onTap: openFullMap,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(24),
                    child: Stack(
                      alignment: Alignment.bottomLeft,
                      children: [
                        SizedBox(
                          height: mapHeight,
                          child: RideMapView(previewOnly: true),
                        ),
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: [
                                Colors.black.withValues(alpha: 0.45),
                                Colors.black.withValues(alpha: 0.12),
                              ],
                              begin: Alignment.bottomCenter,
                              end: Alignment.topCenter,
                            ),
                          ),
                          child: Row(
                            children: [
                              Icon(
                                Icons.electric_bike,
                                color: colorScheme.secondaryContainer,
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  'Tap to explore hubs and live bike availability',
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                              const Icon(
                                Icons.open_in_full,
                                color: Colors.white,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                )
              : _buildMapPlaceholder(theme, mapHeight),

          const SizedBox(height: 18),

          // ---- Nearby header ----
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 2),
            child: Row(
              children: [
                Icon(Icons.electric_bike, color: colorScheme.primary),
                const SizedBox(width: 8),
                _ThemeShimmerText(
                  text: 'Nearby bikes',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(width: 10),
                if (isSearchingNearby)
                  const _NearbySearchingChip()
                else if (bikeLocations.asData != null)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: colorScheme.primary.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '$nearbyBikeCount nearby',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: colorScheme.primary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                const Spacer(),
                TextButton(
                  onPressed: () => Navigator.of(context).pushNamed('/hubs'),
                  child: const Text('See all'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),

          // ---- Bikes horizontal list (cards improved) ----
          SizedBox(
            height: 136,
            child: bikeLocations.when(
              loading: () => const _NearbyBikesShimmer(),
              error: (err, _) => Center(
                child: Text(
                  'Unable to load bikes',
                  style: theme.textTheme.bodyMedium,
                ),
              ),
              data: (bikes) {
                final availableBikes = bikes
                    .where((bike) => !bike.isReserved)
                    .toList();
                if (availableBikes.isEmpty) {
                  return Center(
                    child: Text(
                      'No bikes nearby yet',
                      style: theme.textTheme.bodyMedium,
                    ),
                  );
                }
                return ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  itemCount: availableBikes.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 12),
                  itemBuilder: (context, index) {
                    final bike = availableBikes[index];
                    final isReserved =
                        bike.isReserved ||
                        (session.hasReservation && session.bikeId == bike.id);
                    return _MinimalBikeCard(
                      bike: bike,
                      reserved: isReserved,
                      onReserve: () => reserveBike(bike),
                      onSelect: () => openBikeDetails(bike),
                    );
                  },
                );
              },
            ),
          ),

          const SizedBox(height: 20),

          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: colorScheme.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: colorScheme.primary.withValues(alpha: 0.08),
              ),
            ),
          ),
        ],
      );
    }

    // Active content — scrollable
    Widget buildActiveContent() {
      final rideSync = ref
          .watch(rideStatusStreamProvider)
          .maybeWhen(data: (value) => value, orElse: () => null);
      final activeRide = rideSync?.activeRide;
      final backendEstimatedTotal = activeRide?.estimatedTotalAmount;
      final isPaused = session.isPaused;
      final now = isPaused && session.pausedAt != null
          ? session.pausedAt!
          : clockTick;
      final elapsedSeconds = session.rideStartedAt == null
          ? (metrics.durationMinutes * 60).round()
          : now.difference(session.rideStartedAt!).inSeconds;
      final elapsedMinutes = elapsedSeconds / 60.0;
      final minutesLabel = '${elapsedMinutes.toStringAsFixed(0)} min';
      final perMinuteRate = session.perMinuteRate;
      final hasRate = perMinuteRate != null && perMinuteRate > 0;
      final unlockFee = session.unlockFee ?? 0.0;
      final birrEstimate = backendEstimatedTotal != null
          ? backendEstimatedTotal
          : (hasRate
                ? (unlockFee + (elapsedMinutes * perMinuteRate!)).clamp(0, 9999)
                : 0.0);
      final birrLabel = hasRate || backendEstimatedTotal != null
          ? '${birrEstimate.toStringAsFixed(1)} Br'
          : '-- Br';
      final rateLabel = hasRate
          ? '${perMinuteRate!.toStringAsFixed(1)} Br/min'
          : '-- Br/min';

      return ListView(
        key: const ValueKey('activeRideDashboard'),
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: AppTheme.heroGradient
                    .map((c) => c.withValues(alpha: 0.9))
                    .toList(),
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              children: [
                const CircleAvatar(
                  radius: 24,
                  backgroundColor: Colors.white24,
                  child: Icon(Icons.pedal_bike, color: Colors.white, size: 26),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        isPaused ? 'Ride paused' : 'Ride in progress',
                        style: theme.textTheme.titleLarge?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Bike ${session.bikeId ?? metrics.bikeId} — stay in the zone',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: Colors.white.withValues(alpha: 0.85),
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: refreshMetrics,
                  icon: Icon(Icons.refresh, color: Colors.white),
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          _locationGranted
              ? ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      const SizedBox(
                        height: 280,
                        child: RideMapView(showCenterMarker: false),
                      ),
                      Align(
                        alignment: Alignment.topRight,
                        child: Container(
                          margin: const EdgeInsets.all(14),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.45),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(
                                Icons.navigation_rounded,
                                color: Colors.white,
                                size: 16,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                isPaused ? 'Paused' : 'Tracking',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const Positioned(
                        bottom: 14,
                        child: CircleAvatar(
                          radius: 22,
                          backgroundColor: Colors.white,
                          child: Icon(
                            Icons.electric_bike,
                            color: Colors.black87,
                          ),
                        ),
                      ),
                    ],
                  ),
                )
              : _buildMapPlaceholder(theme, 280),

          const SizedBox(height: 18),

          Row(
            children: [
              _RideStatTile(
                icon: Icons.timer,
                label: 'Duration',
                value: minutesLabel,
                color: colorScheme.secondary,
              ),
              const SizedBox(width: 12),
              _RideStatTile(
                icon: Icons.payments_outlined,
                label: 'Rate',
                value: rateLabel,
                color: colorScheme.primary,
              ),
              const SizedBox(width: 12),
              _RideStatTile(
                icon: Icons.attach_money,
                label: 'Estimated cost',
                value: birrLabel,
                color: Colors.deepOrange,
              ),
            ],
          ),

          const SizedBox(height: 18),

          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: colorScheme.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: colorScheme.primary.withValues(alpha: 0.08),
              ),
            ),
            child: Row(
              children: [
                Icon(Icons.qr_code_scanner, color: colorScheme.primary),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'To end your ride, lock the bike.',
                    style: theme.textTheme.bodyMedium,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: () async {
                    if (isPaused) {
                      await sessionNotifier.resumeRide();
                      messenger.showSnackBar(
                        const SnackBar(content: Text('Ride resumed.')),
                      );
                    } else {
                      await sessionNotifier.pauseRide();
                      messenger.showSnackBar(
                        const SnackBar(content: Text('Ride paused.')),
                      );
                    }
                  },
                  icon: Icon(
                    isPaused ? Icons.play_arrow_rounded : Icons.pause_rounded,
                  ),
                  label: Text(isPaused ? 'Resume' : 'Pause'),
                ),
              ),
            ],
          ),

          const SizedBox(height: 20),
        ],
      );
    }

    return Scaffold(
      body: SafeArea(
        minimum: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        child: session.isRiding ? buildActiveContent() : buildIdleContent(),
      ),
    );
  }
}

/* ---------- Subcomponents: improved nearby card + small tweaks ---------- */

class _ThemeShimmerText extends StatefulWidget {
  const _ThemeShimmerText({required this.text, this.style});

  final String text;
  final TextStyle? style;

  @override
  State<_ThemeShimmerText> createState() => _ThemeShimmerTextState();
}

class _ThemeShimmerTextState extends State<_ThemeShimmerText>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2000),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final baseStyle =
        widget.style ??
        Theme.of(
          context,
        ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700);

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = _controller.value;
        return ShaderMask(
          blendMode: BlendMode.srcIn,
          shaderCallback: (bounds) {
            return LinearGradient(
              begin: Alignment(-1.0 + (t * 2), 0),
              end: Alignment(1.0 + (t * 2), 0),
              colors: [
                colorScheme.primary,
                colorScheme.secondary,
                colorScheme.tertiary,
                colorScheme.primary,
              ],
              stops: const [0.1, 0.4, 0.7, 1.0],
            ).createShader(bounds);
          },
          child: child,
        );
      },
      child: Text(widget.text, style: baseStyle),
    );
  }
}

class _RideStatTile extends StatelessWidget {
  const _RideStatTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          color: color.withValues(alpha: 0.08),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color),
            const SizedBox(height: 6),
            Text(
              value,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
                color: color,
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
      ),
    );
  }
}

class _MinimalBikeCard extends StatefulWidget {
  const _MinimalBikeCard({
    required this.bike,
    required this.reserved,
    required this.onReserve,
    required this.onSelect,
  });

  final BikeLocation bike;
  final bool reserved;
  final Future<void> Function() onReserve;
  final VoidCallback onSelect;

  @override
  State<_MinimalBikeCard> createState() => _MinimalBikeCardState();
}

class _MinimalBikeCardState extends State<_MinimalBikeCard> {
  bool _busy = false;

  double _batteryFraction(int? battery) {
    if (battery == null) return 0.0;
    return (battery.clamp(0, 100)) / 100.0;
  }

  Future<void> _handleReserve() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await widget.onReserve();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not reserve: ${e.toString()}')),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final batteryFrac = _batteryFraction(widget.bike.batteryLevel);
    final colorScheme = Theme.of(context).colorScheme;
    final batteryLabel = widget.bike.batteryLevel == null
        ? '--%'
        : '${widget.bike.batteryLevel}%';
    final bikeLabel = widget.bike.id.toUpperCase();

    return SizedBox(
      width: 160,
      height: 136, // FIXED height to match ListView height -> prevents overflow
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: widget.onSelect,
          child: Ink(
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surface,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: colorScheme.primary.withOpacity(0.06)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.03),
                  blurRadius: 8,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              children: [
                // header (fixed height)
                Container(
                  height: 52,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(12),
                    ),
                    gradient: LinearGradient(
                      colors: AppTheme.heroGradient
                          .map((c) => c.withOpacity(0.32))
                          .toList(),
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                  ),
                  child: const Center(
                    child: Icon(
                      Icons.electric_bike,
                      color: Colors.white,
                      size: 28,
                    ),
                  ),
                ),

                // content area fills remaining space
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(
                      10,
                      8,
                      10,
                      8,
                    ), // slightly reduced vertical padding
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // name (single line)
                        Text(
                          'Bike $bikeLabel',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                              ),
                        ),
                        const SizedBox(height: 6),

                        // battery + button row
                        Row(
                          children: [
                            // battery: progress + label (flexes)
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(6),
                                    child: LinearProgressIndicator(
                                      value: batteryFrac,
                                      minHeight: 6,
                                      backgroundColor: Colors.grey.shade200,
                                      valueColor: AlwaysStoppedAnimation<Color>(
                                        batteryFrac > 0.6
                                            ? Colors.green.shade600
                                            : (batteryFrac > 0.25
                                                  ? Colors.orange
                                                  : Colors.redAccent),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    batteryLabel,
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(fontSize: 11),
                                  ),
                                ],
                              ),
                            ),

                            const SizedBox(width: 8),

                            // Reserve button OR Reserved pill
                            if (widget.reserved)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 6,
                                ),
                                decoration: BoxDecoration(
                                  color: colorScheme.secondary.withOpacity(
                                    0.12,
                                  ),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  'Reserved',
                                  style: Theme.of(context).textTheme.bodySmall
                                      ?.copyWith(
                                        fontWeight: FontWeight.w600,
                                        color: colorScheme.secondary,
                                        fontSize: 12,
                                      ),
                                ),
                              )
                            else
                              SizedBox(
                                height:
                                    34, // fixed button height to keep layout stable
                                child: FilledButton.tonal(
                                  onPressed: _busy ? null : _handleReserve,
                                  style: FilledButton.styleFrom(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 0,
                                    ),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    elevation: 0,
                                    minimumSize: const Size(64, 34),
                                  ),
                                  child: _busy
                                      ? SizedBox(
                                          width: 18,
                                          height: 18,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            valueColor: AlwaysStoppedAnimation(
                                              colorScheme.onPrimary,
                                            ),
                                          ),
                                        )
                                      : Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: const [
                                            Icon(
                                              Icons.check_circle_outline,
                                              size: 14,
                                            ),
                                            SizedBox(width: 6),
                                            Text(
                                              'Reserve',
                                              style: TextStyle(fontSize: 12),
                                            ),
                                          ],
                                        ),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _NearbySearchingChip extends StatefulWidget {
  const _NearbySearchingChip();

  @override
  State<_NearbySearchingChip> createState() => _NearbySearchingChipState();
}

class _NearbySearchingChipState extends State<_NearbySearchingChip>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final base = colorScheme.secondary.withValues(alpha: 0.16);
        final pulse = colorScheme.secondary.withValues(
          alpha: 0.24 + (0.12 * _controller.value),
        );
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: Color.lerp(base, pulse, _controller.value),
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: colorScheme.secondary.withValues(alpha: 0.38),
            ),
          ),
          child: Text(
            'Searching nearby...',
            style: theme.textTheme.labelSmall?.copyWith(
              color: colorScheme.secondary,
              fontWeight: FontWeight.w700,
            ),
          ),
        );
      },
    );
  }
}

class _NearbyBikesShimmer extends StatefulWidget {
  const _NearbyBikesShimmer();

  @override
  State<_NearbyBikesShimmer> createState() => _NearbyBikesShimmerState();
}

class _NearbyBikesShimmerState extends State<_NearbyBikesShimmer>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1300),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 4),
      itemCount: 3,
      separatorBuilder: (_, __) => const SizedBox(width: 12),
      itemBuilder: (_, __) => _NearbyBikeSkeletonTile(animation: _controller),
    );
  }
}

class _NearbyBikeSkeletonTile extends StatelessWidget {
  const _NearbyBikeSkeletonTile({required this.animation});

  final Animation<double> animation;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final base = colorScheme.primary.withValues(alpha: 0.08);
    final highlight = colorScheme.secondary.withValues(alpha: 0.22);

    return AnimatedBuilder(
      animation: animation,
      builder: (context, child) {
        final shimmerDx = -1.2 + (2.4 * animation.value);
        return SizedBox(
          width: 160,
          height: 136,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: ShaderMask(
              shaderCallback: (rect) {
                return LinearGradient(
                  begin: Alignment(shimmerDx - 0.7, -1),
                  end: Alignment(shimmerDx + 0.7, 1),
                  colors: [base, highlight, base],
                  stops: const [0.2, 0.5, 0.8],
                ).createShader(rect);
              },
              blendMode: BlendMode.srcATop,
              child: Container(
                decoration: BoxDecoration(
                  color: colorScheme.surfaceContainerHighest.withValues(
                    alpha: 0.7,
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      height: 44,
                      decoration: BoxDecoration(
                        color: colorScheme.primary.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      width: 90,
                      height: 12,
                      decoration: BoxDecoration(
                        color: colorScheme.onSurface.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      width: double.infinity,
                      height: 8,
                      decoration: BoxDecoration(
                        color: colorScheme.onSurface.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                    const Spacer(),
                    Row(
                      children: [
                        Container(
                          width: 38,
                          height: 10,
                          decoration: BoxDecoration(
                            color: colorScheme.onSurface.withValues(
                              alpha: 0.12,
                            ),
                            borderRadius: BorderRadius.circular(6),
                          ),
                        ),
                        const Spacer(),
                        Container(
                          width: 64,
                          height: 28,
                          decoration: BoxDecoration(
                            color: colorScheme.secondary.withValues(
                              alpha: 0.24,
                            ),
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
