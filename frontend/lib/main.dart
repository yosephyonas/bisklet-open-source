import 'package:bisklet/core/app_bootstrap.dart';
import 'package:bisklet/core/config/mapbox_config.dart';
import 'package:bisklet/core/i18n/app_i18n.dart';
import 'package:bisklet/core/i18n/oromo_localization_fallbacks.dart';
import 'package:bisklet/core/providers/app_providers.dart';
import 'package:bisklet/core/theme/app_theme.dart';
import 'package:bisklet/core/theme/locale_controller.dart';
import 'package:bisklet/core/theme/theme_mode_controller.dart';
import 'package:bisklet/features/auth/presentation/sign_in_page.dart';
import 'package:bisklet/features/auth/presentation/sign_up_page.dart';
import 'package:bisklet/features/history/presentation/ride_history_page.dart';
import 'package:bisklet/features/home/presentation/main_shell.dart';
import 'package:bisklet/features/onboarding/presentation/onboarding_page.dart';
import 'package:bisklet/features/onboarding/presentation/language_selection_page.dart';
import 'package:bisklet/features/passes/presentation/pass_benefits_page.dart';
import 'package:bisklet/features/payments/presentation/payment_methods_page.dart';
import 'package:bisklet/features/ride/presentation/active_ride_page.dart';
import 'package:bisklet/features/ride/presentation/hubs_page.dart';
import 'package:bisklet/features/ride/presentation/scan_to_ride_page.dart';
import 'package:bisklet/features/profile/presentation/notifications_page.dart';
import 'package:bisklet/features/support/presentation/support_page.dart';
import 'package:bisklet/features/support/presentation/safety_guidelines_page.dart';
import 'package:bisklet/ride_map_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  debugPrint(
    'Mapbox access token configured=${MapboxConfig.hasValidAccessToken} '
    '(${MapboxConfig.maskToken(MapboxConfig.accessToken)})',
  );
  debugPrint(
    'Mapbox downloads token configured=${MapboxConfig.hasValidDownloadsToken} '
    '(${MapboxConfig.maskToken(MapboxConfig.downloadsToken)})',
  );

  await AppBootstrap.init();
  runApp(const ProviderScope(child: BiskletApp()));
}

class BiskletApp extends ConsumerWidget {
  const BiskletApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final themeMode = ref.watch(themeModeProvider);
    final appLocale = ref.watch(appLocaleControllerProvider);

    return MaterialApp(
      title: 'Bisklet',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: themeMode,
      locale: appLocale,
      localizationsDelegates: const [
        AppI18n.delegate,
        OromoMaterialLocalizationsDelegate(),
        OromoCupertinoLocalizationsDelegate(),
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: const [
        Locale('en'),
        Locale('am'),
        Locale('om'),
        Locale('ti'),
      ],
      localeResolutionCallback: (locale, supportedLocales) {
        if (locale == null) {
          return const Locale('en');
        }

        for (final supportedLocale in supportedLocales) {
          if (supportedLocale.languageCode == locale.languageCode) {
            return supportedLocale;
          }
        }

        return const Locale('en');
      },
      home: const AuthGate(),
      routes: {
        '/signup': (context) => const SignUpPage(),
        '/signin': (context) => const SignInPage(),
        '/home': (context) => const MainShell(),
        '/ride-history': (context) => const RideHistoryPage(),
        '/support': (context) => const SupportPage(),
        '/payments': (context) => const PaymentMethodsPage(),
        '/pass-benefits': (context) => const PassBenefitsPage(),
        '/ride': (context) => const RideMapPage(),
        '/active-ride': (context) => const ActiveRidePage(),
        '/scanner': (context) => const ScanToRidePage(),
        '/hubs': (context) => const HubsPage(),
        '/notifications': (context) => const NotificationsPage(),
        '/safety-guidelines': (context) => const SafetyGuidelinesPage(),
      },
    );
  }
}

class AuthGate extends ConsumerWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final storage = ref.watch(appStorageProvider);
    return ValueListenableBuilder(
      valueListenable: storage.listenable,
      builder: (context, _, __) {
        final isFirstLaunchWithoutLanguage =
            !storage.hasSeenOnboarding &&
            (storage.appLocaleCode == null || storage.appLocaleCode!.isEmpty);
        if (isFirstLaunchWithoutLanguage) {
          return const LanguageSelectionPage();
        }
        if (!storage.hasSeenOnboarding) {
          return const OnboardingPage();
        }
        if (storage.authToken == null) {
          return const SignInPage();
        }
        return const MainShell();
      },
    );
  }
}
