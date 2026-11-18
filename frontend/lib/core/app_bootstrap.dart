import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:hive_flutter/hive_flutter.dart';

class AppBootstrap {
  AppBootstrap._();

  static bool _initialized = false;

  static Future<void> init() async {
    if (_initialized) {
      return;
    }

    WidgetsFlutterBinding.ensureInitialized();
    try {
      await Hive.initFlutter();
    } on MissingPluginException {
      final tempDir = await Directory.systemTemp.createTemp('bisklet_hive_');
      Hive.init(tempDir.path);
    }
    await Hive.openBox<dynamic>('app');
    _initialized = true;
  }

  static Future<void> clearForTest() async {
    if (!_initialized) {
      await init();
    }
    await Hive.box<dynamic>('app').clear();
  }

  static Box<dynamic> get appBox => Hive.box<dynamic>('app');
}
