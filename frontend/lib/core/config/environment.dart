class AppEnvironment {
  const AppEnvironment._();

  static const String rideUpdatesWsUrl = String.fromEnvironment(
    'RIDE_UPDATES_WS',
    defaultValue: 'wss://example.com/rides',
  );
}
