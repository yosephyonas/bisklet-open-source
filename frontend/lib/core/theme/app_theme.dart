import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppColors {
  const AppColors._();

  static const Color primaryBlue = Color(0xFF1C8585);
  static const Color deepNavy = Color(0xFF1C8585);
  static const Color accentGreen = Color(0xFF1DA27E);
  static const Color softWhite = Color(0xFFFFFFFF);
  static const Color lightBackground = Color(0xFFF2F7F5);
  static const Color lightSurface = Color(0xFFFDFEFE);
  static const Color charcoal = Color(0xFF2F3A3A);
  static const Color secondaryText = Color(0xFF6B7C7A);
  static const Color disabledText = Color(0xFFA0B1AF);
  static const Color onDarkTeal = Color(0xFFFFFFFF);
  static const Color darkBackground = Color(0xFF0B1E1B);
  static const Color darkSurface = Color(0xFF102A26);
  static const Color darkSurfaceElevated = Color(0xFF173733);
  static const Color darkOutline = Color(0xFF2B4F4A);
  static const Color darkMutedText = Color(0xFFB7C6C3);
}

class AppTheme {
  const AppTheme._();

  static final ColorScheme lightColorScheme =
      ColorScheme.fromSeed(
        seedColor: AppColors.primaryBlue,
        brightness: Brightness.light,
      ).copyWith(
        primary: AppColors.primaryBlue,
        onPrimary: AppColors.onDarkTeal,
        secondary: AppColors.accentGreen,
        onSecondary: AppColors.onDarkTeal,
        background: AppColors.lightBackground,
        onBackground: AppColors.charcoal,
        surface: AppColors.lightSurface,
        onSurface: AppColors.charcoal,
        primaryContainer: AppColors.deepNavy,
        onPrimaryContainer: AppColors.onDarkTeal,
        secondaryContainer: AppColors.accentGreen,
        onSecondaryContainer: AppColors.onDarkTeal,
        surfaceVariant: AppColors.lightBackground,
        onSurfaceVariant: AppColors.secondaryText,
        outline: AppColors.secondaryText.withValues(alpha: 0.35),
        outlineVariant: AppColors.secondaryText.withValues(alpha: 0.2),
        surfaceTint: AppColors.primaryBlue,
      );

  static ThemeData lightTheme = ThemeData(
    colorScheme: lightColorScheme,
    useMaterial3: true,
    scaffoldBackgroundColor: AppColors.lightBackground,
    iconTheme: const IconThemeData(
      color: AppColors.charcoal,
    ),
    textTheme: _buildTextTheme(
      base: ThemeData.light().textTheme,
      bodyColor: AppColors.charcoal,
      displayColor: AppColors.charcoal,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: AppColors.lightBackground,
      foregroundColor: AppColors.charcoal,
      iconTheme: const IconThemeData(color: AppColors.charcoal),
      elevation: 0,
      centerTitle: false,
      titleTextStyle: const TextStyle(
        fontWeight: FontWeight.w700,
        fontSize: 20,
        color: AppColors.charcoal,
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.accentGreen,
        foregroundColor: Colors.white,
        textStyle: const TextStyle(fontWeight: FontWeight.w600),
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.primaryBlue,
        side: const BorderSide(color: AppColors.primaryBlue, width: 1.5),
        textStyle: const TextStyle(fontWeight: FontWeight.w600),
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: AppColors.lightSurface,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFE0E6ED)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: AppColors.primaryBlue, width: 1.5),
      ),
      labelStyle: const TextStyle(color: AppColors.secondaryText),
      hintStyle: const TextStyle(color: AppColors.secondaryText),
    ),
    snackBarTheme: const SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: AppColors.primaryBlue,
      contentTextStyle: TextStyle(color: AppColors.onDarkTeal),
    ),
  );

  static final ColorScheme darkColorScheme =
      ColorScheme.fromSeed(
        seedColor: AppColors.primaryBlue,
        brightness: Brightness.dark,
      ).copyWith(
        primary: AppColors.primaryBlue,
        onPrimary: AppColors.onDarkTeal,
        secondary: AppColors.accentGreen,
        onSecondary: Colors.black,
        background: AppColors.darkBackground,
        onBackground: AppColors.onDarkTeal,
        surface: AppColors.darkSurface,
        onSurface: AppColors.onDarkTeal,
        surfaceVariant: AppColors.darkSurfaceElevated,
        onSurfaceVariant: AppColors.darkMutedText,
        outline: AppColors.darkOutline,
        outlineVariant: AppColors.darkOutline.withValues(alpha: 0.7),
        primaryContainer: AppColors.darkSurfaceElevated,
        onPrimaryContainer: AppColors.onDarkTeal,
        secondaryContainer: AppColors.accentGreen,
        onSecondaryContainer: Colors.black,
        surfaceTint: AppColors.primaryBlue,
      );

  static ThemeData darkTheme = ThemeData(
    colorScheme: darkColorScheme,
    useMaterial3: true,
    scaffoldBackgroundColor: AppColors.darkBackground,
    iconTheme: const IconThemeData(
      color: AppColors.onDarkTeal,
    ),
    textTheme: _buildTextTheme(
      base: ThemeData.dark().textTheme,
      bodyColor: AppColors.onDarkTeal,
      displayColor: AppColors.onDarkTeal,
      mutedText: AppColors.darkMutedText,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: AppColors.darkBackground,
      foregroundColor: AppColors.onDarkTeal,
      iconTheme: const IconThemeData(color: AppColors.onDarkTeal),
      elevation: 0,
      centerTitle: false,
      titleTextStyle: const TextStyle(
        fontWeight: FontWeight.w700,
        fontSize: 20,
        color: AppColors.onDarkTeal,
      ),
    ),
    cardColor: AppColors.darkSurfaceElevated,
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.accentGreen,
        foregroundColor: Colors.black,
        textStyle: const TextStyle(fontWeight: FontWeight.w600),
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.onDarkTeal,
        side: const BorderSide(color: AppColors.darkOutline, width: 1.5),
        textStyle: const TextStyle(fontWeight: FontWeight.w600),
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: AppColors.darkSurfaceElevated,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: AppColors.darkOutline),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: AppColors.primaryBlue, width: 1.5),
      ),
      labelStyle: const TextStyle(color: AppColors.darkMutedText),
      hintStyle: const TextStyle(color: AppColors.darkMutedText),
    ),
    snackBarTheme: const SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: AppColors.darkSurfaceElevated,
      contentTextStyle: TextStyle(color: AppColors.onDarkTeal),
    ),
  );

  static const List<Color> heroGradient = [
    AppColors.primaryBlue,
    AppColors.accentGreen,
  ];

  static TextTheme _buildTextTheme({
    required TextTheme base,
    required Color bodyColor,
    required Color displayColor,
    Color? mutedText,
  }) {
    final bodyFont = GoogleFonts.plusJakartaSansTextTheme(base);
    final displayFont = GoogleFonts.soraTextTheme(base);
    final merged = bodyFont.merge(displayFont);
    return merged
        .apply(bodyColor: bodyColor, displayColor: displayColor)
        .copyWith(
          bodySmall: TextStyle(color: mutedText ?? AppColors.secondaryText),
          labelSmall: TextStyle(color: mutedText ?? AppColors.secondaryText),
          labelMedium: TextStyle(color: mutedText ?? AppColors.secondaryText),
        );
  }
}
