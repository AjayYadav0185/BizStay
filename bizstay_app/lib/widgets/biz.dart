import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

/// Shared BizStay look: small helpers so every screen reads the same.
class Biz {
  static const primary = Color(0xFF0F766E);
  static const bg = Color(0xFFF3F4F6);

  static final inr0 = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 0);
  static final inr2 = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 2);

  static String money(dynamic v, {bool paise = false}) {
    final d = v is num ? v.toDouble() : double.tryParse('$v') ?? 0;
    return (paise ? inr2 : inr0).format(d);
  }

  static Color statusColor(String s) {
    switch (s.toLowerCase()) {
      case 'paid':
      case 'verified':
      case 'resolved':
      case 'closed':
      case 'active':
      case 'available':
        return const Color(0xFF15803D);
      case 'overdue':
      case 'open':
      case 'high':
        return const Color(0xFFB91C1C);
      case 'partially_paid':
      case 'pending':
      case 'in_progress':
      case 'medium':
      case 'notice_period':
      case 'partial':
        return const Color(0xFFB45309);
      default:
        return const Color(0xFF475569);
    }
  }

  static Widget chip(String text, {Color? color}) {
    final c = color ?? statusColor(text);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: c.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: c.withValues(alpha: 0.35)),
      ),
      child: Text(text.replaceAll('_', ' ').toUpperCase(),
          style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: c)),
    );
  }

  static Widget statCard(String label, String value, {IconData? icon, Color? tint}) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(children: [
          if (icon != null)
            Container(
              padding: const EdgeInsets.all(9),
              decoration: BoxDecoration(
                  color: (tint ?? primary).withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12)),
              child: Icon(icon, color: tint ?? primary),
            ),
          if (icon != null) const SizedBox(width: 12),
          Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                Text(label,
                    style: const TextStyle(fontSize: 12, color: Colors.black54)),
                const SizedBox(height: 2),
                Text(value,
                    style: const TextStyle(
                        fontSize: 18, fontWeight: FontWeight.w800)),
              ])),
        ]),
      ),
    );
  }

  static Widget errorBox(String message, {VoidCallback? onRetry}) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.cloud_off_outlined, size: 44, color: Colors.black38),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          if (onRetry != null) ...[
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ]),
      ),
    );
  }
}
