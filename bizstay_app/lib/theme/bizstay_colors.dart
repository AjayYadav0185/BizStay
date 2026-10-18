import 'package:flutter/material.dart';

/// BizStay brand palette — premium hospitality identity.
///
/// Primary: deep navy [#0F172A], Accent: warm amber/gold [#F59E0B],
/// Body: soft slate grays, Status: distinct success / warning / error.
abstract final class BizStayColors {
  // ── Brand ──────────────────────────────────────────────
  static const navy900 = Color(0xFF0F172A); // primary
  static const navy800 = Color(0xFF1E293B);
  static const navy700 = Color(0xFF334155);
  static const navy600 = Color(0xFF475569);

  static const gold500 = Color(0xFFF59E0B); // accent / highlight
  static const gold600 = Color(0xFFD97706);
  static const gold100 = Color(0xFFFEF3C7);
  static const gold50 = Color(0xFFFFFBEB);

  // ── Slate scale (body text + surfaces) ─────────────────
  static const slate50 = Color(0xFFF8FAFC);
  static const slate100 = Color(0xFFF1F5F9);
  static const slate200 = Color(0xFFE2E8F0);
  static const slate300 = Color(0xFFCBD5E1);
  static const slate400 = Color(0xFF94A3B8);
  static const slate500 = Color(0xFF64748B);
  static const slate600 = Color(0xFF475569);
  static const slate700 = Color(0xFF334155);

  // ── Status (rent lifecycle — must stay distinct) ───────
  static const success = Color(0xFF16A34A);
  static const successBg = Color(0xFFDCFCE7);
  static const successDark = Color(0xFF15803D);

  static const warning = Color(0xFFD97706);
  static const warningBg = Color(0xFFFEF3C7);

  static const error = Color(0xFFDC2626);
  static const errorBg = Color(0xFFFEE2E2);
  static const errorDark = Color(0xFFB91C1C);

  static const info = Color(0xFF0284C7);
  static const infoBg = Color(0xFFE0F2FE);

  // ── Dark theme surfaces ────────────────────────────────
  static const darkBg = Color(0xFF020617);
  static const darkSurface = Color(0xFF0F172A);
  static const darkCard = Color(0xFF1E293B);
  static const darkBorder = Color(0xFF334155);

  /// Map any backend status string → semantic color.
  static Color statusColor(String raw) {
    switch (raw.toLowerCase().trim()) {
      case 'paid':
      case 'verified':
      case 'resolved':
      case 'closed':
      case 'active':
      case 'available':
      case 'vacant':
        return successDark;
      case 'overdue':
      case 'open':
      case 'high':
      case 'unpaid':
      case 'failed':
        return errorDark;
      case 'partially_paid':
      case 'partial':
      case 'pending':
      case 'in_progress':
      case 'medium':
      case 'notice_period':
      case 'occupied':
      case 'blocked':
        return warning;
      case 'low':
      case 'booked':
        return info;
      default:
        return slate600;
    }
  }

  /// Soft tinted background for a status color.
  static Color statusBg(Color c) {
    if (c == successDark || c == success) return successBg;
    if (c == errorDark || c == error) return errorBg;
    if (c == warning || c == gold600) return warningBg;
    if (c == info) return infoBg;
    return slate100;
  }
}
