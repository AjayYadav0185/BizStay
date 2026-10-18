import 'package:flutter/material.dart';

import 'bizstay_colors.dart';

/// Premium Material 3 ThemeData for BizStay (light only).
/// Deep navy primary + warm amber accent, slate surfaces.
abstract final class BizStayTheme {
  static const lightScheme = ColorScheme(
    brightness: Brightness.light,
    primary: BizStayColors.navy900,
    onPrimary: Colors.white,
    primaryContainer: Color(0xFFE2E8F0),
    onPrimaryContainer: BizStayColors.navy900,
    secondary: BizStayColors.gold600,
    onSecondary: Colors.white,
    secondaryContainer: BizStayColors.gold100,
    onSecondaryContainer: Color(0xFF92400E),
    tertiary: BizStayColors.navy700,
    onTertiary: Colors.white,
    tertiaryContainer: Color(0xFFE2E8F0),
    onTertiaryContainer: BizStayColors.navy900,
    error: BizStayColors.error,
    onError: Colors.white,
    errorContainer: BizStayColors.errorBg,
    onErrorContainer: BizStayColors.errorDark,
    surface: Colors.white,
    onSurface: BizStayColors.navy900,
    surfaceContainerLowest: Colors.white,
    surfaceContainerLow: BizStayColors.slate50,
    surfaceContainer: BizStayColors.slate100,
    surfaceContainerHigh: Color(0xFFEAF0F6),
    surfaceContainerHighest: BizStayColors.slate200,
    onSurfaceVariant: BizStayColors.slate600,
    outline: BizStayColors.slate300,
    outlineVariant: BizStayColors.slate200,
    shadow: BizStayColors.navy900,
    scrim: BizStayColors.navy900,
    inverseSurface: BizStayColors.navy900,
    onInverseSurface: Colors.white,
    inversePrimary: BizStayColors.gold500,
    surfaceTint: BizStayColors.navy900,
  );

  static TextTheme textTheme(ColorScheme scheme) {
    final base = ThemeData(useMaterial3: true, colorScheme: scheme).textTheme;
    return base.copyWith(
      displaySmall: base.displaySmall?.copyWith(
          fontWeight: FontWeight.w800, letterSpacing: -0.5,
          color: scheme.onSurface),
      headlineSmall: base.headlineSmall?.copyWith(
          fontWeight: FontWeight.w800, letterSpacing: -0.25,
          color: scheme.onSurface),
      titleLarge: base.titleLarge?.copyWith(
          fontWeight: FontWeight.w700, color: scheme.onSurface),
      titleMedium: base.titleMedium?.copyWith(
          fontWeight: FontWeight.w700, color: scheme.onSurface),
      titleSmall: base.titleSmall?.copyWith(
          fontWeight: FontWeight.w700, letterSpacing: 0.2,
          color: scheme.onSurfaceVariant),
      bodyLarge: base.bodyLarge?.copyWith(
          color: scheme.onSurface, height: 1.45),
      bodyMedium: base.bodyMedium?.copyWith(
          color: scheme.onSurfaceVariant, height: 1.45),
      bodySmall: base.bodySmall?.copyWith(color: scheme.onSurfaceVariant),
      labelLarge: base.labelLarge?.copyWith(
          fontWeight: FontWeight.w700, letterSpacing: 0.2),
      labelSmall: base.labelSmall?.copyWith(
          fontWeight: FontWeight.w700, letterSpacing: 1.0,
          color: scheme.onSurfaceVariant),
    );
  }
}

/// Light + dark ThemeData factories. Kept as an extension-style
/// companion so bizstay_theme.dart stays under edit-size limits.
abstract final class BizStayThemeData {
  static ThemeData light() {
    const scheme = BizStayTheme.lightScheme;
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: BizStayColors.slate100,
      textTheme: BizStayTheme.textTheme(scheme),
      appBarTheme: const AppBarTheme(
        backgroundColor: BizStayColors.navy900,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 2,
        centerTitle: false,
        titleTextStyle: TextStyle(fontSize: 19,
            fontWeight: FontWeight.w800, color: Colors.white),
        iconTheme: IconThemeData(color: Colors.white),
        actionsIconTheme: IconThemeData(color: Colors.white),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: BizStayColors.slate200),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: BizStayColors.navy900,
          foregroundColor: Colors.white,
          minimumSize: const Size(48, 50),
          textStyle: const TextStyle(
              fontSize: 15, fontWeight: FontWeight.w700),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14)),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: BizStayColors.gold500,
          foregroundColor: BizStayColors.navy900,
          minimumSize: const Size(48, 50),
          textStyle:
              const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: BizStayColors.navy900,
          minimumSize: const Size(48, 50),
          side: const BorderSide(color: BizStayColors.slate300),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14)),
          textStyle:
              const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: BizStayColors.navy800,
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        hintStyle: const TextStyle(color: BizStayColors.slate400),
        labelStyle: const TextStyle(color: BizStayColors.slate600),
        floatingLabelStyle:
            const TextStyle(color: BizStayColors.navy800),
        prefixIconColor: BizStayColors.slate500,
        suffixIconColor: BizStayColors.slate500,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: BizStayColors.slate300),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: BizStayColors.slate300),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:
              const BorderSide(color: BizStayColors.navy900, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: BizStayColors.error),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: Colors.white,
        selectedColor: BizStayColors.navy900,
        disabledColor: BizStayColors.slate100,
        labelStyle: const TextStyle(fontSize: 13,
            fontWeight: FontWeight.w700, color: BizStayColors.slate700),
        secondaryLabelStyle: const TextStyle(fontSize: 13,
            fontWeight: FontWeight.w700, color: Colors.white),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: BizStayColors.slate300),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: BizStayColors.gold100,
        labelTextStyle: const WidgetStatePropertyAll(
          TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
        ),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const IconThemeData(
                color: BizStayColors.navy900, size: 24);
          }
          return const IconThemeData(
              color: BizStayColors.slate500, size: 24);
        }),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        showDragHandle: true,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20)),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: BizStayColors.navy900,
        contentTextStyle: const TextStyle(color: Colors.white),
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12)),
        behavior: SnackBarBehavior.floating,
      ),
      dividerTheme: const DividerThemeData(
          color: BizStayColors.slate200, thickness: 1, space: 1),
      listTileTheme: const ListTileThemeData(
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        titleTextStyle: TextStyle(fontSize: 15,
            fontWeight: FontWeight.w700, color: BizStayColors.navy900),
        subtitleTextStyle:
            TextStyle(fontSize: 13, color: BizStayColors.slate600),
        iconColor: BizStayColors.slate500,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: BizStayColors.navy900,
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: BizStayColors.navy900,
        foregroundColor: Colors.white,
        elevation: 3,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16)),
        extendedTextStyle: const TextStyle(
            fontSize: 14, fontWeight: FontWeight.w800),
        extendedIconLabelSpacing: 8,
        extendedPadding:
            EdgeInsets.symmetric(horizontal: 18, vertical: 14),
      ),
      searchBarTheme: SearchBarThemeData(
        backgroundColor: const WidgetStatePropertyAll(Colors.white),
        elevation: const WidgetStatePropertyAll(0),
        side: WidgetStatePropertyAll(
            BorderSide(color: BizStayColors.slate200)),
        shape: WidgetStatePropertyAll(
            RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14))),
        hintStyle: WidgetStatePropertyAll(const TextStyle(
            color: BizStayColors.slate400, fontSize: 14)),
      ),
      tabBarTheme: const TabBarThemeData(
        labelColor: Colors.white,
        unselectedLabelColor: Colors.white70,
        indicatorColor: BizStayColors.gold500,
        labelStyle: TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
      ),
    );
  }
}

/// Spacing / radius tokens so screens stay consistent.
abstract final class BizStaySpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 20.0;
  static const pagePadding = EdgeInsets.all(16);
  static const cardPadding = EdgeInsets.all(16);
  static const cardRadius = 16.0;
}
