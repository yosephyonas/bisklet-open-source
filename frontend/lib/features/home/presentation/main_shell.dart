import "dart:async";

import "package:flutter/material.dart";
import "package:flutter_riverpod/flutter_riverpod.dart";

import "../../ride/application/ride_session_controller.dart";
import "../../ride/application/ride_metrics_controller.dart";
import "../../ride/application/ride_api_providers.dart";
import "../../ride/application/ride_status_providers.dart";
import "../../ride/application/ride_location_providers.dart";
import "../../ride/application/bike_tracking_providers.dart";
import "../../ride/domain/ride_summary.dart";
import "../../ride/presentation/widgets/ride_completion_sheet.dart";
import "../../ride/services/bike_tracking_service.dart";
import "../../profile/presentation/profile_page.dart";
import "../../wallet/presentation/wallet_page.dart";
import "../application/home_navigation_controller.dart";
import "package:bisklet/ride_map_page.dart";
import "package:bisklet/core/utils/haptics.dart";
import "package:bisklet/core/providers/app_providers.dart";
import "package:bisklet/core/i18n/app_i18n.dart";

final _pages = <Widget>[
  const RideMapPage(showActionButton: false),
  const WalletPage(),
  const ProfilePage(),
];

class MainShell extends ConsumerWidget {
  const MainShell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colorScheme = Theme.of(context).colorScheme;
    final currentIndex = ref.watch(homeNavigationControllerProvider);
    final storage = ref.watch(appStorageProvider);
    final navigationNotifier = ref.read(
      homeNavigationControllerProvider.notifier,
    );
    final selectedBike = ref.watch(selectedBikeProvider);
    final rideSession = ref.watch(rideSessionControllerProvider);
    final rideMetrics = ref.watch(rideMetricsProvider);
    final rideFlow = ref.read(rideFlowServiceProvider);
    final rideLocationService = ref.read(rideLocationServiceProvider);
    final bikeLocations = ref.watch(bikeLocationsStreamProvider);
    final rideSessionNotifier = ref.read(
      rideSessionControllerProvider.notifier,
    );
    final messenger = ScaffoldMessenger.of(context);

    Future<void> handleRemoteRideEnded(RideSummary? summary) async {
      final currentSession = ref.read(rideSessionControllerProvider);
      if (!currentSession.isRiding) {
        return;
      }
      final rideSnapshot = ref.read(rideMetricsProvider);
      final bikeLabel = currentSession.bikeId ?? rideSnapshot.bikeId;
      await rideSessionNotifier.endRide();
      if (!context.mounted) return;
      Haptics.medium();
      await showRideCompletionSheet(
        context: context,
        metrics: rideSnapshot,
        perMinuteRate: summary?.perMinuteRate ?? rideSession.perMinuteRate ?? 0,
        summary: summary,
        bikeLabelOverride: bikeLabel,
      );
      if (!context.mounted) return;
      messenger.showSnackBar(
        SnackBar(content: Text(context.l10n.tr('snack.ride_ended_lock'))),
      );
    }

    ref.listen(rideStatusStreamProvider, (previous, next) {
      next.whenData((event) {
        if (event.activeRide != null) {
          unawaited(
            rideSessionNotifier.attachRideInfo(
              rideId: event.activeRide!.id,
              perMinuteRate: event.activeRide!.perMinuteRate,
              unlockFee: event.activeRide!.unlockFee,
            ),
          );
        }
        if (!event.hasEnded) return;
        unawaited(handleRemoteRideEnded(event.endedRide));
      });
    });

    ref.listen(rideSessionControllerProvider, (previous, next) {
      if (next.isRiding) {
        unawaited(rideLocationService.start(rideId: next.rideId));
      } else {
        unawaited(rideLocationService.stop());
      }
    });

    final showDockedCard =
        currentIndex == 0 && (selectedBike != null || rideSession.isRiding);

    Future<void> startRideFromLockCode(String lockCode) async {
      final cleaned = lockCode.trim();
      if (cleaned.isEmpty) return;
      if (rideSession.isRiding) {
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
                'Unlock failed (${result.unlock.status}). Try again.',
              ),
            ),
          );
          return;
        }
        await rideSessionNotifier.reserveBike(
          cleaned,
          lockImei: result.scan.imei,
        );
        await rideSessionNotifier.startRide(
          startedAt: result.activeRide?.startedAt,
        );
        final perMinuteRate = result.activeRide?.perMinuteRate ?? fallbackRate;
        final unlockFee = result.activeRide?.unlockFee;
        await rideSessionNotifier.attachRideInfo(
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
      } catch (e) {
        messenger.showSnackBar(
          SnackBar(
            content: Text('${context.l10n.tr('snack.unlock_failed')}: $e'),
          ),
        );
      }
    }

    Widget rideIcon(bool selected) {
      final baseIcon = Icon(
        selected ? Icons.electric_bike : Icons.electric_bike_outlined,
      );
      if (rideSession.isIdle) {
        return baseIcon;
      }
      return Stack(
        clipBehavior: Clip.none,
        children: [
          baseIcon,
          Positioned(
            right: -2,
            top: -2,
            child: Container(
              height: 10,
              width: 10,
              decoration: BoxDecoration(
                color: rideSession.isRiding
                    ? colorScheme.secondary
                    : colorScheme.primary,
                shape: BoxShape.circle,
              ),
            ),
          ),
        ],
      );
    }

    Widget? buildRideFab() {
      if (currentIndex != 0) {
        return null;
      }
      if (selectedBike != null) {
        return BikeDetailsCard(
          bike: selectedBike,
          onClose: () => ref.read(selectedBikeProvider.notifier).state = null,
          margin: EdgeInsets.zero,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          attachBottom: true,
        );
      }
      if (rideSession.isRiding) {
        return ActiveRideMiniCard(
          session: rideSession,
          onPauseRide: () {
            if (rideSession.isPaused) {
              rideSessionNotifier.resumeRide();
              messenger.showSnackBar(
                SnackBar(content: Text(context.l10n.tr('snack.ride_resumed'))),
              );
            } else {
              rideSessionNotifier.pauseRide();
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
      if (rideSession.hasReservation) {
        return _RideActionFab(
          label: "Start ride",
          icon: Icons.play_arrow,
          color: colorScheme.secondary,
          onPressed: () async {
            final bikeId = rideSession.bikeId;
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
      return _RideActionFab(
        label: "Scan to ride",
        icon: Icons.qr_code_scanner,
        color: colorScheme.secondary,
        isEmphasized: true,
        onPressed: () async {
          Haptics.medium();
          final result = await Navigator.of(context).pushNamed('/scanner');
          if (!context.mounted) return;
          final bikeId = result is String ? result.trim() : "";
          if (bikeId.isEmpty) return;
          await startRideFromLockCode(bikeId);
        },
      );
    }

    return ValueListenableBuilder(
      valueListenable: storage.listenable,
      builder: (context, _, __) {
        final needsLocation = !storage.isLocationPermissionGranted;
        final needsNotification = !storage.isNotificationPermissionGranted;
        final showPermissionTopup =
            currentIndex == 0 && (needsLocation || needsNotification);

        return Scaffold(
          extendBody: true,
          body: IndexedStack(index: currentIndex, children: _pages),
          bottomNavigationBar:
              showPermissionTopup ||
                  rideSession.isRiding ||
                  selectedBike != null
              ? null
              : Padding(
                  padding: const EdgeInsets.fromLTRB(28, 8, 28, 30),
                  child: _MinimalNavBar(
                    colorScheme: colorScheme,
                    currentIndex: currentIndex,
                    items: [
                      _NavItemData(
                        label: context.l10n.tr('nav.ride'),
                        iconBuilder: (selected) => rideIcon(selected),
                      ),
                      _NavItemData(
                        label: context.l10n.tr('nav.wallet'),
                        iconBuilder: (selected) => Icon(
                          selected
                              ? Icons.account_balance_wallet
                              : Icons.account_balance_wallet_outlined,
                        ),
                      ),
                      _NavItemData(
                        label: context.l10n.tr('nav.profile'),
                        iconBuilder: (selected) => Icon(
                          selected ? Icons.person : Icons.person_outline,
                        ),
                      ),
                    ],
                    onSelect: (index) {
                      Haptics.selection();
                      navigationNotifier.setIndex(index);
                    },
                  ),
                ),
          floatingActionButtonLocation: showDockedCard
              ? const _NoGapCenterFloatFabLocation()
              : FloatingActionButtonLocation.centerFloat,
          floatingActionButtonAnimator:
              FloatingActionButtonAnimator.noAnimation,
          floatingActionButton: showPermissionTopup ? null : buildRideFab(),
        );
      },
    );
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
        heroTag: "rideAction",
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

class _MinimalNavBar extends StatelessWidget {
  const _MinimalNavBar({
    required this.colorScheme,
    required this.currentIndex,
    required this.items,
    required this.onSelect,
  });

  final ColorScheme colorScheme;
  final int currentIndex;
  final List<_NavItemData> items;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final navShadowColor = isDark
        ? Colors.black.withValues(alpha: 0.32)
        : Colors.black.withValues(alpha: 0.12);
    final navBackgroundColor = isDark
        ? colorScheme.surfaceContainerHigh
        : colorScheme.surface;
    final navBorderColor = isDark
        ? colorScheme.outline.withValues(alpha: 0.4)
        : colorScheme.outlineVariant.withValues(alpha: 0.2);
    final selectedItemBg = isDark
        ? colorScheme.secondaryContainer.withValues(alpha: 0.7)
        : colorScheme.secondary.withValues(alpha: 0.16);
    final selectedItemFg = isDark
        ? colorScheme.onSecondaryContainer
        : colorScheme.onSurface;
    final unselectedItemFg = isDark
        ? colorScheme.onSurface.withValues(alpha: 0.78)
        : colorScheme.onSurface.withValues(alpha: 0.6);

    final navContent = Padding(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      child: Row(
        children: List.generate(items.length, (index) {
          final selected = index == currentIndex;
          final item = items[index];
          return Expanded(
            child: InkWell(
              onTap: () => onSelect(index),
              borderRadius: BorderRadius.circular(24),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                curve: Curves.easeOut,
                padding: const EdgeInsets.symmetric(vertical: 6),
                decoration: BoxDecoration(
                  color: selected ? selectedItemBg : Colors.transparent,
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconTheme(
                      data: IconThemeData(
                        color: selected ? selectedItemFg : unselectedItemFg,
                      ),
                      child: item.iconBuilder(selected),
                    ),
                    const SizedBox(height: 4),
                    AnimatedDefaultTextStyle(
                      duration: const Duration(milliseconds: 150),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: selected
                            ? FontWeight.w700
                            : FontWeight.w500,
                        color: selected ? selectedItemFg : unselectedItemFg,
                      ),
                      child: Text(item.label),
                    ),
                  ],
                ),
              ),
            ),
          );
        }),
      ),
    );

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: navShadowColor,
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Container(
          decoration: BoxDecoration(
            color: navBackgroundColor,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: navBorderColor),
          ),
          child: navContent,
        ),
      ),
    );
  }
}

class _NavItemData {
  _NavItemData({required this.label, required this.iconBuilder});

  final String label;
  final Widget Function(bool selected) iconBuilder;
}

class _NoGapCenterFloatFabLocation extends FloatingActionButtonLocation {
  const _NoGapCenterFloatFabLocation();

  @override
  Offset getOffset(ScaffoldPrelayoutGeometry scaffoldGeometry) {
    final fabSize = scaffoldGeometry.floatingActionButtonSize;
    final fabX = (scaffoldGeometry.scaffoldSize.width - fabSize.width) / 2;
    final fabY = scaffoldGeometry.contentBottom - fabSize.height;
    return Offset(fabX, fabY);
  }
}
