import 'package:bisklet/features/ride/application/bike_tracking_providers.dart';
import 'package:bisklet/features/ride/application/ride_session_controller.dart';
import 'package:bisklet/ride_map_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class HubsPage extends ConsumerWidget {
  const HubsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bikesAsync = ref.watch(bikeLocationsStreamProvider);
    final session = ref.watch(rideSessionControllerProvider);
    final sessionNotifier = ref.read(rideSessionControllerProvider.notifier);
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final messenger = ScaffoldMessenger.of(context);

    Future<void> reserveBike(String bikeId) async {
      if (session.isRiding) {
        messenger.showSnackBar(
          const SnackBar(content: Text('End your current ride to reserve.')),
        );
        return;
      }
      await sessionNotifier.reserveBike(bikeId);
      if (!context.mounted) return;
      messenger.showSnackBar(
        SnackBar(content: Text('Bike $bikeId reserved for 10 minutes')),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Nearby bikes')),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(bikeLocationsStreamProvider);
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(22),
              child: SizedBox(
                height: 220,
                child: Stack(
                  children: [
                    const Positioned.fill(child: RideMapView(previewOnly: true)),
                    Positioned(
                      right: 12,
                      top: 12,
                      child: FilledButton.tonalIcon(
                        onPressed: () =>
                            Navigator.of(context).pushNamed('/ride'),
                        icon: const Icon(Icons.map_outlined),
                        label: const Text('Open map'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'All bikes',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 12),
            bikesAsync.when(
              loading: () => const Padding(
                padding: EdgeInsets.symmetric(vertical: 32),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (error, _) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Column(
                  children: [
                    Text(
                      'Unable to load bikes right now.',
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      error.toString(),
                      style: theme.textTheme.bodySmall,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () =>
                          ref.invalidate(bikeLocationsStreamProvider),
                      icon: const Icon(Icons.refresh),
                      label: const Text('Retry'),
                    ),
                  ],
                ),
              ),
              data: (bikes) {
                if (bikes.isEmpty) {
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 24),
                    child: Column(
                      children: [
                        Icon(
                          Icons.pedal_bike_outlined,
                          size: 36,
                          color: colorScheme.onSurface.withValues(alpha: 0.5),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          'No bikes available nearby yet.',
                          style: theme.textTheme.bodyLarge,
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  );
                }
                return Column(
                  children: bikes.map((bike) {
                    final batteryLabel = bike.batteryLevel == null
                        ? '--% battery'
                        : '${bike.batteryLevel}% battery';
                    final lastUpdated =
                        TimeOfDay.fromDateTime(bike.lastUpdated)
                            .format(context);
                    final isReserved =
                        session.hasReservation && session.bikeId == bike.id;
                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: colorScheme.surface,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(
                          color: colorScheme.primary.withValues(alpha: 0.08),
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.04),
                            blurRadius: 16,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          CircleAvatar(
                            radius: 22,
                            backgroundColor:
                                colorScheme.secondary.withValues(alpha: 0.16),
                            child: Icon(
                              Icons.electric_bike,
                              color: colorScheme.secondary,
                            ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Bike ${bike.id.toUpperCase()}',
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  '$batteryLabel - Updated $lastUpdated',
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: theme.textTheme.bodySmall?.color
                                        ?.withValues(alpha: 0.7),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          if (isReserved)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                color: colorScheme.secondary
                                    .withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Text(
                                'Reserved',
                                style: theme.textTheme.labelSmall?.copyWith(
                                  color: colorScheme.secondary,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            )
                          else
                            FilledButton.tonal(
                              onPressed: () => reserveBike(bike.id),
                              child: const Text('Reserve'),
                            ),
                        ],
                      ),
                    );
                  }).toList(),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
