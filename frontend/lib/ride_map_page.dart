import 'dart:async';
import 'dart:math';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart' as geo;
import 'package:mapbox_maps_flutter/mapbox_maps_flutter.dart';
import 'package:permission_handler/permission_handler.dart';

import 'core/config/mapbox_config.dart';
import 'core/i18n/app_i18n.dart';
import 'core/network/api_client.dart';
import 'core/providers/app_providers.dart';
import 'core/providers/location_providers.dart';
import 'core/utils/haptics.dart';
import 'features/ride/application/bike_tracking_providers.dart';
import 'features/ride/application/ride_api_providers.dart';
import 'features/ride/application/ride_metrics_controller.dart';
import 'features/ride/application/ride_session_controller.dart';
import 'features/ride/services/bike_tracking_service.dart';

const double _nearbyDistanceMeters = 200;
final selectedBikeProvider = StateProvider<BikeLocation?>((ref) => null);

class RideMapPage extends ConsumerStatefulWidget {
  const RideMapPage({super.key, this.showActionButton = true});

  final bool showActionButton;

  @override
  ConsumerState<RideMapPage> createState() => _RideMapPageState();
}

class _RideMapPageState extends ConsumerState<RideMapPage>
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
    Haptics.selection();
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

  Widget _buildPermissionTopup(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colorScheme.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colorScheme.primary.withValues(alpha: 0.2)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.12),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.lock, color: colorScheme.primary),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  context.l10n.tr('map.enable_permissions_title'),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            context.l10n.tr('map.enable_permissions_body'),
            style: theme.textTheme.bodySmall?.copyWith(
              color: colorScheme.onSurface.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _PermissionChip(
                label: context.l10n.tr('map.location'),
                enabled: _locationGranted,
              ),
              const SizedBox(width: 8),
              _PermissionChip(
                label: context.l10n.tr('profile.notifications'),
                enabled: _notificationGranted,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerRight,
            child: FilledButton(
              onPressed: _requestingPermissions ? null : _requestPermissions,
              child: _requestingPermissions
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(context.l10n.tr('map.allow')),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMapPlaceholder(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    return Container(
      color: colorScheme.surface,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.my_location, color: colorScheme.primary, size: 34),
              const SizedBox(height: 10),
              Text(
                context.l10n.tr('map.location_required_title'),
                textAlign: TextAlign.center,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                context.l10n.tr('map.location_required_body'),
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: colorScheme.onSurface.withValues(alpha: 0.7),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(rideSessionControllerProvider);
    final rideMetrics = ref.watch(rideMetricsProvider);
    final bikeLocations = ref.watch(bikeLocationsStreamProvider);
    final selectedBike = ref.watch(selectedBikeProvider);
    final bikes = bikeLocations.value ?? const <BikeLocation>[];
    final userPosition = ref.watch(currentUserPositionProvider);
    final nearbyDistances = userPosition == null
        ? const <double>[]
        : bikes
              .where((bike) => !bike.isReserved)
              .map(
                (bike) =>
                    bike.distanceMeters ??
                    geo.Geolocator.distanceBetween(
                      userPosition.latitude,
                      userPosition.longitude,
                      bike.latitude,
                      bike.longitude,
                    ),
              )
              .where(
                (distanceMeters) => distanceMeters <= _nearbyDistanceMeters,
              )
              .toList();
    final nearbyCount = nearbyDistances.length;
    final nearbyWalkMinutes = nearbyDistances.isEmpty
        ? null
        : max(1, (nearbyDistances.reduce(min) / 80).round());
    final messenger = ScaffoldMessenger.of(context);
    final sessionNotifier = ref.read(rideSessionControllerProvider.notifier);
    final rideFlow = ref.read(rideFlowServiceProvider);

    Widget buildNearbyBanner() {
      final count = nearbyCount;
      if (userPosition == null || count == 0) {
        return const SizedBox.shrink();
      }
      return _NearbyBikesBanner(count: count, walkMinutes: nearbyWalkMinutes!);
    }

    Future<void> startRideFromLockCode(String lockCode) async {
      final cleaned = lockCode.trim();
      if (cleaned.isEmpty) return;
      if (session.isRiding) {
        messenger.showSnackBar(
          SnackBar(content: Text(context.l10n.tr('snack.end_current_ride'))),
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
                '${context.l10n.tr('snack.unlock_failed')} (${result.unlock.status}). ${context.l10n.tr('common.retry')}',
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
          SnackBar(content: Text(context.l10n.tr('snack.bike_unlocked'))),
        );
      } on ApiException catch (e) {
        messenger.showSnackBar(
          SnackBar(
            content: Text(
              '${context.l10n.tr('snack.unlock_failed')}: ${e.message}',
            ),
          ),
        );
      } catch (e) {
        messenger.showSnackBar(
          SnackBar(
            content: Text('${context.l10n.tr('snack.unlock_failed')}: $e'),
          ),
        );
      }
    }

    Widget? buildRideFab() {
      if (session.isRiding) {
        return ActiveRideMiniCard(
          session: session,
          onPauseRide: () {
            if (session.isPaused) {
              sessionNotifier.resumeRide();
              messenger.showSnackBar(
                SnackBar(content: Text(context.l10n.tr('snack.ride_resumed'))),
              );
            } else {
              sessionNotifier.pauseRide();
              messenger.showSnackBar(
                SnackBar(content: Text(context.l10n.tr('snack.ride_paused'))),
              );
            }
          },
          onExpand: () => Navigator.of(context).pushNamed('/active-ride'),
          margin: EdgeInsets.zero,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          attachBottom: true,
        );
      }

      if (session.hasReservation) {
        return _RideActionFab(
          label: context.l10n.tr('map.start_ride'),
          icon: Icons.play_arrow_rounded,
          color: Theme.of(context).colorScheme.secondary,
          onPressed: () async {
            Haptics.medium();
            final bikeId = session.bikeId;
            if (bikeId == null || bikeId.trim().isEmpty) {
              messenger.showSnackBar(
                SnackBar(
                  content: Text(context.l10n.tr('snack.scan_bike_start')),
                ),
              );
              return;
            }
            await startRideFromLockCode(bikeId);
          },
        );
      }

      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            child: buildNearbyBanner(),
          ),
          _RideActionFab(
            label: context.l10n.tr('map.scan_to_ride'),
            icon: Icons.qr_code_scanner,
            color: Theme.of(context).colorScheme.primary,
            isEmphasized: true,
            onPressed: () async {
              Haptics.selection();
              final result = await Navigator.of(context).pushNamed('/scanner');
              if (!context.mounted) return;
              final bikeId = result is String ? result.trim() : '';
              if (bikeId.isEmpty) return;
              await startRideFromLockCode(bikeId);
            },
          ),
        ],
      );
    }

    final showMap = _locationGranted;
    final showPermissionTopup = !_locationGranted;

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: showMap
                ? const RideMapView()
                : _buildMapPlaceholder(context),
          ),
          if (showPermissionTopup)
            Positioned.fill(
              child: AbsorbPointer(
                child: BackdropFilter(
                  filter: ui.ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                  child: Container(color: Colors.black.withValues(alpha: 0.25)),
                ),
              ),
            ),
          if (showPermissionTopup)
            Positioned.fill(
              child: SafeArea(
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 420),
                      child: _buildPermissionTopup(context),
                    ),
                  ),
                ),
              ),
            ),
          if (!showPermissionTopup &&
              !widget.showActionButton &&
              selectedBike == null &&
              !session.isRiding &&
              nearbyCount > 0)
            Positioned(
              left: 16,
              right: 16,
              top: 16,
              child: SafeArea(
                minimum: const EdgeInsets.only(top: 8),
                child: buildNearbyBanner(),
              ),
            ),
        ],
      ),
      floatingActionButtonLocation: widget.showActionButton
          ? FloatingActionButtonLocation.centerDocked
          : null,
      floatingActionButton: widget.showActionButton && !showPermissionTopup
          ? Padding(
              padding: EdgeInsets.only(
                bottom: session.isRiding || selectedBike != null ? 0 : 72,
              ),
              child: selectedBike == null
                  ? buildRideFab()
                  : BikeDetailsCard(
                      bike: selectedBike,
                      onClose: () =>
                          ref.read(selectedBikeProvider.notifier).state = null,
                      margin: EdgeInsets.zero,
                      borderRadius: const BorderRadius.vertical(
                        top: Radius.circular(24),
                      ),
                      attachBottom: true,
                    ),
            )
          : null,
    );
  }
}

class RideMapView extends ConsumerStatefulWidget {
  const RideMapView({
    super.key,
    this.previewOnly = false,
    this.showCenterMarker = true,
  });

  final bool previewOnly;
  final bool showCenterMarker;

  @override
  ConsumerState<RideMapView> createState() => _RideMapViewState();
}

class _NearbyBikesBanner extends StatefulWidget {
  const _NearbyBikesBanner({required this.count, required this.walkMinutes});

  final int count;
  final int walkMinutes;

  @override
  State<_NearbyBikesBanner> createState() => _NearbyBikesBannerState();
}

class _NearbyBikesBannerState extends State<_NearbyBikesBanner>
    with TickerProviderStateMixin {
  late final AnimationController _pulseController;
  late final AnimationController _shimmerController;
  late final Animation<double> _pulseScale;
  late final Animation<double> _pulseOpacity;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);
    _shimmerController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    )..repeat();
    _pulseScale = Tween<double>(begin: 1.0, end: 1.08).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );
    _pulseOpacity = Tween<double>(begin: 0.65, end: 1.0).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _shimmerController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final isDark = theme.brightness == Brightness.dark;
    final pulseT = Curves.easeInOut.transform(_pulseController.value);
    final subtitleColor = isDark
        ? Color.lerp(
            colorScheme.onSurface.withValues(alpha: 0.72),
            colorScheme.onSurface,
            pulseT,
          )
        : Color.lerp(const Color(0xFF2A5059), const Color(0xFF16363E), pulseT);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: isDark
            ? colorScheme.surfaceContainerHighest.withValues(alpha: 0.9)
            : Colors.white.withValues(alpha: 0.93),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: isDark
              ? colorScheme.outline.withValues(alpha: 0.35)
              : colorScheme.outline.withValues(alpha: 0.2),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.18 : 0.1),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          AnimatedBuilder(
            animation: _pulseController,
            builder: (context, child) {
              return Opacity(
                opacity: _pulseOpacity.value,
                child: Transform.scale(scale: _pulseScale.value, child: child),
              );
            },
            child: Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                color: colorScheme.primary.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(
                Icons.pedal_bike,
                color: colorScheme.primary,
                size: 24,
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${widget.count} bikes nearby',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: colorScheme.onSurface,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                AnimatedBuilder(
                  animation: _pulseController,
                  builder: (context, child) {
                    return Text(
                      '${widget.walkMinutes} ${context.l10n.tr('map.min_walk_nearest')}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: subtitleColor,
                        fontWeight: FontWeight.w600,
                      ),
                    );
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
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

class _RideMapViewState extends ConsumerState<RideMapView> {
  MapboxMap? _map;
  PointAnnotationManager? _pointManager;
  PointAnnotationManager? _userPointManager;
  ProviderSubscription<AsyncValue<List<BikeLocation>>>? _locationsSub;
  ProviderSubscription<geo.Position?>? _userPositionSub;
  StreamSubscription<geo.Position>? _positionSub;
  var _latestLocations = <BikeLocation>[];
  bool _initialCameraApplied = false;
  bool _isLocating = false;
  bool _autoCenteredOnUser = false;
  final Map<String, Uint8List> _markerCache = {};
  final Map<String, BikeLocation> _annotationBikeMap = {};
  Uint8List? _userMarkerBytes;
  geo.Position? _lastUserPosition;

  @override
  void initState() {
    super.initState();
    _locationsSub = ref.listenManual<AsyncValue<List<BikeLocation>>>(
      bikeLocationsStreamProvider,
      (previous, next) {
        next.whenData((value) {
          _latestLocations = value;
          _syncAnnotations();
          _centerCameraIfNeeded();
        });
      },
      fireImmediately: true,
    );
    _userPositionSub = ref.listenManual<geo.Position?>(
      currentUserPositionProvider,
      (previous, next) {
        ref.read(bikeTrackingServiceProvider).updateUserPosition(next);
        unawaited(_syncUserMarker(next));
      },
      fireImmediately: true,
    );
    _startUserLocationStream();
  }

  @override
  void dispose() {
    unawaited(_pointManager?.deleteAll());
    unawaited(_userPointManager?.deleteAll());
    _pointManager = null;
    _userPointManager = null;
    _map = null;
    _locationsSub?.close();
    _userPositionSub?.close();
    _positionSub?.cancel();
    super.dispose();
  }

  Future<void> _startUserLocationStream() async {
    try {
      if (!await geo.Geolocator.isLocationServiceEnabled()) {
        return;
      }
      var permission = await geo.Geolocator.checkPermission();
      if (permission == geo.LocationPermission.denied) {
        permission = await geo.Geolocator.requestPermission();
      }
      if (permission == geo.LocationPermission.denied ||
          permission == geo.LocationPermission.deniedForever) {
        ref.read(currentUserPositionProvider.notifier).state = null;
        return;
      }

      final settings = const geo.LocationSettings(
        accuracy: geo.LocationAccuracy.high,
        distanceFilter: 5,
      );
      await _positionSub?.cancel();
      _positionSub =
          geo.Geolocator.getPositionStream(locationSettings: settings).listen(
            (position) {
              ref.read(currentUserPositionProvider.notifier).state = position;
            },
            onError: (_) {
              ref.read(currentUserPositionProvider.notifier).state = null;
            },
          );
    } catch (_) {
      ref.read(currentUserPositionProvider.notifier).state = null;
    }
  }

  Future<void> _onMapCreated(MapboxMap map) async {
    _map = map;
    final manager = await map.annotations.createPointAnnotationManager();
    manager.addOnPointAnnotationClickListener(
      _BikeAnnotationClickListener((annotationId) {
        final bike = _annotationBikeMap[annotationId];
        if (bike != null) {
          Haptics.selection();
          _showBikeSheet(bike);
        }
      }),
    );
    _pointManager = manager;
    _userPointManager = await map.annotations.createPointAnnotationManager();
    _syncAnnotations();
    unawaited(_syncUserMarker(ref.read(currentUserPositionProvider)));
    _centerCameraIfNeeded(force: true);
    unawaited(_autoCenterOnUserIfAllowed());
  }

  Future<void> _autoCenterOnUserIfAllowed() async {
    if (_autoCenteredOnUser || _map == null) {
      return;
    }
    final storage = ref.read(appStorageProvider);
    if (!storage.isLocationPermissionGranted) {
      return;
    }

    try {
      if (!await geo.Geolocator.isLocationServiceEnabled()) {
        return;
      }
      final permission = await geo.Geolocator.checkPermission();
      if (permission == geo.LocationPermission.denied ||
          permission == geo.LocationPermission.deniedForever) {
        return;
      }

      final position = await geo.Geolocator.getCurrentPosition(
        desiredAccuracy: geo.LocationAccuracy.high,
      );
      _autoCenteredOnUser = true;
      _initialCameraApplied = true;
      ref.read(currentUserPositionProvider.notifier).state = position;

      await _map?.easeTo(
        CameraOptions(
          center: Point(
            coordinates: Position(position.longitude, position.latitude),
          ),
          zoom: widget.previewOnly ? 14.0 : 15.5,
        ),
        MapAnimationOptions(duration: 900),
      );
    } catch (_) {
      // If auto-centering fails, we still fall back to bike cluster.
    }
  }

  Future<void> _centerOnUserLocation() async {
    if (_isLocating) return;
    Haptics.selection();
    setState(() => _isLocating = true);

    try {
      if (!await geo.Geolocator.isLocationServiceEnabled()) {
        _showLocationMessage(context.l10n.tr('map.location_services_off'));
        ref.read(currentUserPositionProvider.notifier).state = null;
        return;
      }

      var permission = await geo.Geolocator.checkPermission();
      if (permission == geo.LocationPermission.denied) {
        permission = await geo.Geolocator.requestPermission();
      }
      if (permission == geo.LocationPermission.denied ||
          permission == geo.LocationPermission.deniedForever) {
        _showLocationMessage(context.l10n.tr('map.location_permission_denied'));
        ref.read(currentUserPositionProvider.notifier).state = null;
        return;
      }

      final position = await geo.Geolocator.getCurrentPosition(
        desiredAccuracy: geo.LocationAccuracy.high,
      );
      ref.read(currentUserPositionProvider.notifier).state = position;

      await _map?.easeTo(
        CameraOptions(
          center: Point(
            coordinates: Position(position.longitude, position.latitude),
          ),
          zoom: widget.previewOnly ? 14.0 : 15.5,
        ),
        MapAnimationOptions(duration: 1000),
      );
    } catch (_) {
      _showLocationMessage(context.l10n.tr('map.location_fetch_failed'));
    } finally {
      if (mounted) {
        setState(() => _isLocating = false);
      }
    }
  }

  void _showLocationMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  void _showBikeSheet(BikeLocation bike) {
    ref.read(selectedBikeProvider.notifier).state = bike;
  }

  void _centerCameraIfNeeded({bool force = false}) {
    if (_map == null || _latestLocations.isEmpty) {
      return;
    }
    if (_initialCameraApplied && !force) {
      return;
    }
    final avgLat =
        _latestLocations.fold<double>(0, (sum, b) => sum + b.latitude) /
        _latestLocations.length;
    final avgLng =
        _latestLocations.fold<double>(0, (sum, b) => sum + b.longitude) /
        _latestLocations.length;
    _initialCameraApplied = true;
    unawaited(
      _map?.easeTo(
        CameraOptions(
          center: Point(coordinates: Position(avgLng, avgLat)),
          zoom: widget.previewOnly ? 11.5 : 13.5,
        ),
        MapAnimationOptions(duration: 1200),
      ),
    );
  }

  Future<void> _syncAnnotations() async {
    final manager = _pointManager;
    final map = _map;
    if (manager == null || map == null) {
      return;
    }
    await manager.deleteAll();
    _annotationBikeMap.clear();
    if (_latestLocations.isEmpty) {
      return;
    }

    final options = await Future.wait(
      _latestLocations.map((bike) async {
        final bytes = await _markerBytesFor(bike.batteryLevel);
        return PointAnnotationOptions(
          geometry: Point(coordinates: Position(bike.longitude, bike.latitude)),
          image: bytes,
          iconAnchor: IconAnchor.CENTER,
        );
      }),
    );
    final created = await manager.createMulti(options);
    for (var i = 0; i < created.length; i++) {
      final annotation = created[i];
      if (annotation == null) {
        continue;
      }
      final id = annotation.id;
      if (id == null) {
        continue;
      }
      _annotationBikeMap[id] = _latestLocations[i];
    }
  }

  Future<void> _syncUserMarker(geo.Position? position) async {
    final manager = _userPointManager;
    if (manager == null) return;

    if (position == null) {
      _lastUserPosition = null;
      await manager.deleteAll();
      return;
    }

    if (_lastUserPosition != null) {
      final delta = geo.Geolocator.distanceBetween(
        _lastUserPosition!.latitude,
        _lastUserPosition!.longitude,
        position.latitude,
        position.longitude,
      );
      if (delta < 1) {
        return;
      }
    }
    _lastUserPosition = position;

    _userMarkerBytes ??= await _buildUserMarkerBytes();
    await manager.deleteAll();
    await manager.create(
      PointAnnotationOptions(
        geometry: Point(
          coordinates: Position(position.longitude, position.latitude),
        ),
        image: _userMarkerBytes,
        iconAnchor: IconAnchor.CENTER,
      ),
    );
  }

  Future<Uint8List> _markerBytesFor(int? batteryLevel) async {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final markerBackground = isDark
        ? theme.colorScheme.secondaryContainer
        : theme.colorScheme.primary;
    final markerIconColor = isDark
        ? theme.colorScheme.onSecondaryContainer
        : theme.colorScheme.onPrimary;
    final markerStrokeColor = isDark
        ? theme.colorScheme.outline.withValues(alpha: 0.65)
        : theme.colorScheme.surface;
    final key =
        '${batteryLevel ?? 'na'}:${markerBackground.value}:${markerIconColor.value}:${markerStrokeColor.value}';
    final cached = _markerCache[key];
    if (cached != null) return cached;

    final bytes = await _buildBikeMarkerBytes(
      batteryLevel,
      markerBackground,
      markerIconColor,
      markerStrokeColor,
    );
    _markerCache[key] = bytes;
    return bytes;
  }

  Future<Uint8List> _buildBikeMarkerBytes(
    int? batteryLevel,
    Color backgroundColor,
    Color iconColor,
    Color strokeColor,
  ) async {
    const size = 28.0;
    const pointerHeight = 6.0;
    const pointerWidth = 10.0;
    final pixelRatio = MediaQuery.of(context).devicePixelRatio;

    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    canvas.scale(pixelRatio);

    final background = Paint()..color = backgroundColor;
    final stroke = Paint()
      ..color = strokeColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.6;
    final rect = const Rect.fromLTWH(0, 0, size, size);
    canvas.drawOval(rect, background);
    canvas.drawOval(Rect.fromLTWH(0.8, 0.8, size - 1.6, size - 1.6), stroke);

    final iconText = TextSpan(
      text: String.fromCharCode(Icons.pedal_bike.codePoint),
      style: TextStyle(
        color: iconColor,
        fontSize: 14,
        fontFamily: Icons.pedal_bike.fontFamily,
        package: Icons.pedal_bike.fontPackage,
      ),
    );
    final iconPainter = TextPainter(
      text: iconText,
      textDirection: TextDirection.ltr,
    )..layout();
    iconPainter.paint(canvas, const Offset(7, 7));

    final pointer = Path()
      ..moveTo(size / 2, size + pointerHeight)
      ..lineTo((size - pointerWidth) / 2, size - 1)
      ..lineTo((size + pointerWidth) / 2, size - 1)
      ..close();
    canvas.drawPath(pointer, background);
    canvas.drawLine(
      Offset((size - pointerWidth) / 2, size - 1),
      Offset(size / 2, size + pointerHeight),
      stroke,
    );
    canvas.drawLine(
      Offset(size / 2, size + pointerHeight),
      Offset((size + pointerWidth) / 2, size - 1),
      stroke,
    );

    // Icon-only marker: keep it simple and aligned with app colors.

    final picture = recorder.endRecording();
    final image = await picture.toImage(
      (size * pixelRatio).round(),
      ((size + pointerHeight) * pixelRatio).round(),
    );
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    return byteData!.buffer.asUint8List();
  }

  Future<Uint8List> _buildUserMarkerBytes() async {
    const size = 26.0;
    final pixelRatio = MediaQuery.of(context).devicePixelRatio;

    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    canvas.scale(pixelRatio);

    final center = Offset(size / 2, size / 2);
    final outer = Paint()..color = Colors.white;
    final inner = Paint()..color = const Color(0xFF2F80ED);
    final dot = Paint()..color = Colors.white;

    canvas.drawCircle(center, size / 2, outer);
    canvas.drawCircle(center, (size / 2) - 3, inner);
    canvas.drawCircle(center, 3, dot);

    final picture = recorder.endRecording();
    final image = await picture.toImage(
      (size * pixelRatio).round(),
      (size * pixelRatio).round(),
    );
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    return byteData!.buffer.asUint8List();
  }

  @override
  Widget build(BuildContext context) {
    if (!MapboxConfig.hasValidAccessToken) {
      return const _MissingMapboxToken();
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.previewOnly ? 22 : 0),
      child: Stack(
        fit: StackFit.expand,
        children: [
          MapWidget(
            cameraOptions: CameraOptions(
              center: Point(
                coordinates: Position(
                  MapboxConfig.defaultLongitude,
                  MapboxConfig.defaultLatitude,
                ),
              ),
              zoom: widget.previewOnly ? 11.0 : 13.2,
            ),
            styleUri: MapboxStyles.STANDARD,
            textureView: true,
            onMapCreated: _onMapCreated,
            onTapListener: (_) {
              if (ref.read(selectedBikeProvider) != null) {
                Haptics.selection();
                ref.read(selectedBikeProvider.notifier).state = null;
              }
            },
          ),
          if (widget.showCenterMarker &&
              !widget.previewOnly &&
              ref.watch(currentUserPositionProvider) == null)
            IgnorePointer(
              child: Center(
                child: Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.15),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white70),
                  ),
                  child: const Icon(Icons.my_location, color: Colors.white),
                ),
              ),
            ),
          Positioned(
            right: 16,
            bottom: widget.previewOnly ? 16 : 72,
            child: SafeArea(
              minimum: const EdgeInsets.only(bottom: 12, right: 4),
              child: FloatingActionButton.small(
                heroTag: 'locationFab',
                onPressed: _isLocating ? null : _centerOnUserLocation,
                backgroundColor: Colors.white,
                foregroundColor: Theme.of(context).colorScheme.primary,
                child: _isLocating
                    ? SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      )
                    : const Icon(Icons.my_location, size: 20),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

Color _lightenColor(Color color, double amount) {
  final hsl = HSLColor.fromColor(color);
  final lightness = (hsl.lightness + amount).clamp(0.0, 1.0);
  return hsl.withLightness(lightness).toColor();
}

class _MissingMapboxToken extends StatelessWidget {
  const _MissingMapboxToken();

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.grey.shade900,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.map_outlined, size: 42, color: Colors.white70),
          const SizedBox(height: 12),
          Text(
            context.l10n.tr('map.mapbox_missing_title'),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(color: Colors.white),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 6),
          Text(
            context.l10n.tr('map.mapbox_missing_body'),
            style: const TextStyle(color: Colors.white60),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

class BikeDetailsCard extends ConsumerWidget {
  const BikeDetailsCard({
    required this.bike,
    this.onClose,
    this.margin = const EdgeInsets.fromLTRB(16, 0, 16, 18),
    this.borderRadius = const BorderRadius.all(Radius.circular(24)),
    this.attachBottom = false,
  });

  final BikeLocation bike;
  final VoidCallback? onClose;
  final EdgeInsets margin;
  final BorderRadius borderRadius;
  final bool attachBottom;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final battery = bike.batteryLevel;
    final batteryText = battery == null ? '--% battery' : '$battery% battery';
    final userPosition = ref.watch(currentUserPositionProvider);
    final distanceMeters = userPosition == null
        ? null
        : geo.Geolocator.distanceBetween(
            userPosition.latitude,
            userPosition.longitude,
            bike.latitude,
            bike.longitude,
          );
    final walkMinutes = distanceMeters == null
        ? null
        : max(1, (distanceMeters / 80).round());
    final rangeKm = battery == null ? null : (battery * 0.15);
    final rangeText = rangeKm == null
        ? '--km range'
        : '${rangeKm.toStringAsFixed(0)}km range';
    final distanceText = distanceMeters == null
        ? '--m'
        : '${distanceMeters.round()}m';

    return SafeArea(
      top: false,
      bottom: false,
      child: Container(
        margin: margin,
        padding: const EdgeInsets.fromLTRB(18, 16, 18, 20),
        decoration: BoxDecoration(
          color: colorScheme.surface,
          borderRadius: borderRadius,
          boxShadow: attachBottom
              ? [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.12),
                    blurRadius: 18,
                    offset: const Offset(0, -6),
                  ),
                ]
              : [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.18),
                    blurRadius: 22,
                    offset: const Offset(0, 10),
                  ),
                ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: colorScheme.primary.withOpacity(0.18),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    'FASTEST ROUTE',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: colorScheme.onSurface,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.2,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: colorScheme.surface.withOpacity(0.9),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color: colorScheme.outlineVariant.withOpacity(0.4),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.directions_walk,
                        size: 14,
                        color: colorScheme.onSurface.withOpacity(0.7),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        walkMinutes == null
                            ? '-- min walk'
                            : '$walkMinutes min walk',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: colorScheme.onSurface.withOpacity(0.7),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                const Spacer(),
                IconButton(
                  onPressed: onClose,
                  icon: const Icon(Icons.pedal_bike),
                  style: IconButton.styleFrom(
                    backgroundColor: colorScheme.secondary.withOpacity(0.2),
                    foregroundColor: colorScheme.secondary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              'Bike #${bike.id.toUpperCase()}',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: colorScheme.secondary,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  batteryText,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: colorScheme.onSurface.withOpacity(0.7),
                  ),
                ),
                const SizedBox(width: 10),
                Text(
                  '• $distanceText ~ $rangeText',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colorScheme.onSurface.withOpacity(0.6),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: FilledButton.icon(
                    onPressed: onClose,
                    icon: const Icon(Icons.qr_code_scanner),
                    label: Text(context.l10n.tr('map.unlock_bike')),
                  ),
                ),
                const SizedBox(width: 12),
                IconButton(
                  onPressed: onClose,
                  icon: const Icon(Icons.navigation),
                  style: IconButton.styleFrom(
                    backgroundColor: colorScheme.surface.withOpacity(0.9),
                    foregroundColor: colorScheme.onSurface.withOpacity(0.8),
                    side: BorderSide(
                      color: colorScheme.outlineVariant.withOpacity(0.4),
                    ),
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

class ActiveRideMiniCard extends ConsumerWidget {
  const ActiveRideMiniCard({
    required this.session,
    required this.onPauseRide,
    required this.onExpand,
    this.margin = const EdgeInsets.fromLTRB(16, 0, 16, 18),
    this.borderRadius = const BorderRadius.all(Radius.circular(24)),
    this.attachBottom = false,
  });

  final RideSessionState session;
  final VoidCallback onPauseRide;
  final VoidCallback onExpand;
  final EdgeInsets margin;
  final BorderRadius borderRadius;
  final bool attachBottom;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final metrics = ref.watch(rideMetricsProvider);
    final clockTick = ref
        .watch(rideClockProvider)
        .maybeWhen(data: (value) => value, orElse: DateTime.now);
    final isPaused = session.isPaused;
    final now = isPaused && session.pausedAt != null
        ? session.pausedAt!
        : clockTick;
    final elapsedSeconds = session.rideStartedAt == null
        ? (metrics.durationMinutes * 60).round()
        : now.difference(session.rideStartedAt!).inSeconds;
    final durationLabel = _formatDuration(elapsedSeconds);
    final perMinuteRate = session.perMinuteRate;
    final rateValue = perMinuteRate != null && perMinuteRate > 0
        ? perMinuteRate.toStringAsFixed(1)
        : '--';
    final batteryLabel = metrics.batteryLevel <= 0
        ? '--%'
        : '${metrics.batteryLevel}%';

    return SafeArea(
      top: false,
      bottom: false,
      child: Container(
        margin: margin,
        decoration: BoxDecoration(
          color: colorScheme.surface,
          borderRadius: borderRadius,
          border: Border.all(
            color: colorScheme.outlineVariant.withOpacity(0.35),
          ),
          boxShadow: attachBottom
              ? [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.10),
                    blurRadius: 22,
                    offset: const Offset(0, -8),
                  ),
                ]
              : [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.16),
                    blurRadius: 24,
                    offset: const Offset(0, 12),
                  ),
                ],
        ),
        child: InkWell(
          onTap: onExpand,
          borderRadius: borderRadius,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _MetricBlock(
                        label: context.l10n.tr('map.duration'),
                        value: durationLabel,
                      ),
                    ),
                    Expanded(
                      child: _MetricBlock(
                        label: context.l10n.tr('map.birr_per_min'),
                        value: rateValue,
                      ),
                    ),
                    Expanded(
                      child: _MetricBlock(
                        label: context.l10n.tr('map.battery'),
                        value: batteryLabel,
                        accent: colorScheme.secondary,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: onPauseRide,
                        icon: Icon(isPaused ? Icons.play_arrow : Icons.pause),
                        label: Text(isPaused ? 'Resume' : 'Pause'),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          textStyle: const TextStyle(
                            fontWeight: FontWeight.w700,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(18),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Icon(
                      Icons.info_outline,
                      size: 18,
                      color: colorScheme.onSurface.withOpacity(0.6),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        context.l10n.tr('map.ride_protected'),
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colorScheme.onSurface.withOpacity(0.6),
                        ),
                      ),
                    ),
                    TextButton(
                      onPressed: () =>
                          Navigator.of(context).pushNamed('/support'),
                      child: Text(context.l10n.tr('profile.support')),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MetricBlock extends StatelessWidget {
  const _MetricBlock({required this.label, required this.value, this.accent});

  final String label;
  final String value;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final tone = accent ?? colorScheme.onSurface;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: theme.textTheme.labelSmall?.copyWith(
            letterSpacing: 0.4,
            color: colorScheme.onSurface.withOpacity(0.5),
            fontWeight: FontWeight.w700,
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 10),
        Text(
          value,
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w700,
            fontSize: 22,
            color: tone,
          ),
        ),
      ],
    );
  }
}

String _formatDuration(int seconds) {
  if (seconds < 0) return '--:--';
  final minutes = seconds ~/ 60;
  final remainder = seconds % 60;
  return '${minutes.toString().padLeft(2, '0')}:${remainder.toString().padLeft(2, '0')}';
}

class _BikeAnnotationClickListener extends OnPointAnnotationClickListener {
  _BikeAnnotationClickListener(this.onTap);

  final void Function(String annotationId) onTap;

  @override
  bool onPointAnnotationClick(PointAnnotation annotation) {
    onTap(annotation.id);
    return true;
  }
}

class _RideActionFab extends StatefulWidget {
  const _RideActionFab({
    required this.label,
    required this.icon,
    required this.onPressed,
    required this.color,
    this.isEmphasized = false,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;
  final Color color;
  final bool isEmphasized;

  @override
  State<_RideActionFab> createState() => _RideActionFabState();
}

class _RideActionFabState extends State<_RideActionFab>
    with SingleTickerProviderStateMixin {
  late final AnimationController _glowController;

  @override
  void initState() {
    super.initState();
    _glowController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    );
    if (widget.isEmphasized) {
      _glowController.repeat(reverse: true);
    }
  }

  @override
  void didUpdateWidget(covariant _RideActionFab oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.isEmphasized != widget.isEmphasized) {
      if (widget.isEmphasized) {
        _glowController.repeat(reverse: true);
      } else {
        _glowController.stop();
      }
    }
  }

  @override
  void dispose() {
    _glowController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _glowController,
      builder: (context, child) {
        final glow = widget.isEmphasized ? _glowController.value : 0.0;
        return Container(
          decoration: BoxDecoration(
            boxShadow: [
              if (widget.isEmphasized)
                BoxShadow(
                  color: widget.color.withValues(alpha: 0.25 + glow * 0.25),
                  blurRadius: 18 + glow * 10,
                  spreadRadius: 1 + glow * 2,
                  offset: const Offset(0, 8),
                ),
            ],
          ),
          child: child,
        );
      },
      child: FloatingActionButton.extended(
        heroTag: '_rideAction',
        elevation: 2,
        backgroundColor: widget.color,
        foregroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
        extendedPadding: EdgeInsets.symmetric(
          horizontal: widget.isEmphasized ? 34 : 24,
          vertical: widget.isEmphasized ? 16 : 12,
        ),
        onPressed: widget.onPressed,
        icon: Icon(widget.icon, size: widget.isEmphasized ? 24 : 20),
        label: Text(
          widget.label,
          style: TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: widget.isEmphasized ? 16 : 14,
          ),
        ),
      ),
    );
  }
}

String _formatCountdown(Duration? duration) {
  if (duration == null) return '--';
  if (duration.inMinutes > 0) {
    final minutes = duration.inMinutes;
    final seconds = duration.inSeconds % 60;
    return '${minutes}m ${seconds.toString().padLeft(2, '0')}s';
  }
  return '${max(duration.inSeconds, 0)}s';
}
