import 'package:flutter/foundation.dart';

abstract class AppLogger {
  void logEvent(String name, {Map<String, dynamic>? parameters});

  void logError(
    String message, {
    Object? error,
    StackTrace? stackTrace,
    Map<String, dynamic>? context,
  });

  void logInfo(String message, {Map<String, dynamic>? context});
}

class DebugAppLogger implements AppLogger {
  const DebugAppLogger();

  @override
  void logEvent(String name, {Map<String, dynamic>? parameters}) {
    debugPrint('[event] $name ${parameters ?? {}}');
  }

  @override
  void logError(
    String message, {
    Object? error,
    StackTrace? stackTrace,
    Map<String, dynamic>? context,
  }) {
    debugPrint('[error] $message error=$error context=${context ?? {}}');
    if (stackTrace != null) {
      debugPrint(stackTrace.toString());
    }
  }

  @override
  void logInfo(String message, {Map<String, dynamic>? context}) {
    debugPrint('[info] $message ${context ?? {}}');
  }
}
