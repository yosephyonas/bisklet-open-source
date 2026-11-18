import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart' as geo;

final currentUserPositionProvider = StateProvider<geo.Position?>(
  (ref) => null,
);
