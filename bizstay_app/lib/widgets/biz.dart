import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../theme/bizstay_colors.dart';
import 'bizstay_ui.dart' show StatusChip, StatCard, ErrorState;

/// Shared BizStay look: small helpers so every screen reads the same.
///
/// New code should prefer [BizStayColors], [BizStayThemeData] and the
/// widgets in `bizstay_ui.dart`. These helpers stay as thin shims so
/// existing screens keep working.
class Biz {
  static const primary = BizStayColors.navy900;
  static const accent = BizStayColors.gold500;
  static const bg = BizStayColors.slate100;

  static final inr0 = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 0);
  static final inr2 = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 2);

  static String money(dynamic v, {bool paise = false}) {
    final d = v is num ? v.toDouble() : double.tryParse('$v') ?? 0;
    return (paise ? inr2 : inr0).format(d);
  }

  static Color statusColor(String s) => BizStayColors.statusColor(s);

  static Widget chip(String text, {Color? color}) =>
      StatusChip(text, color: color);

  static Widget statCard(String label, String value,
      {IconData? icon, Color? tint}) => StatCard(
        label: label,
        value: value,
        icon: icon,
        tint: tint,
      );

  static Widget errorBox(String message, {VoidCallback? onRetry}) =>
      ErrorState(message, onRetry: onRetry);
}
