import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

/// Provides English framework localizations when app locale is unsupported by
/// Flutter's built-in global delegates.
///
/// Flutter's built-in Material/Cupertino localizations do not currently include
/// Oromo (`om`) or Tigrinya (`ti`). We inject this fallback to avoid missing
/// localization exceptions while still letting AppI18n serve app strings.
class OromoMaterialLocalizationsDelegate
    extends LocalizationsDelegate<MaterialLocalizations> {
  const OromoMaterialLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) =>
      locale.languageCode == 'om' || locale.languageCode == 'ti';

  @override
  Future<MaterialLocalizations> load(Locale locale) {
    return SynchronousFuture<MaterialLocalizations>(
      const DefaultMaterialLocalizations(),
    );
  }

  @override
  bool shouldReload(
    covariant LocalizationsDelegate<MaterialLocalizations> old,
  ) {
    return false;
  }
}

class OromoCupertinoLocalizationsDelegate
    extends LocalizationsDelegate<CupertinoLocalizations> {
  const OromoCupertinoLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) =>
      locale.languageCode == 'om' || locale.languageCode == 'ti';

  @override
  Future<CupertinoLocalizations> load(Locale locale) {
    return SynchronousFuture<CupertinoLocalizations>(
      const DefaultCupertinoLocalizations(),
    );
  }

  @override
  bool shouldReload(
    covariant LocalizationsDelegate<CupertinoLocalizations> old,
  ) {
    return false;
  }
}
