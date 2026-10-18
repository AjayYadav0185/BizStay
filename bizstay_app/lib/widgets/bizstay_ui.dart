import 'package:flutter/material.dart';

import '../theme/bizstay_colors.dart';

/// Reusable UI kit: status chips, money text, stat cards.
/// All widgets read from ThemeData first (light/dark safe).
class StatusChip extends StatelessWidget {
  const StatusChip(this.text, {super.key, this.color});

  final String text;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final c = color ?? BizStayColors.statusColor(text);
    final bg = BizStayColors.statusBg(c);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: c.withValues(alpha: 0.4)),
      ),
      child: Text(
        text.replaceAll('_', ' ').toUpperCase(),
        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800,
            letterSpacing: 0.6, color: c),
      ),
    );
  }
}

/// Tabular-figure money text — keeps rent columns aligned.
class MoneyText extends StatelessWidget {
  const MoneyText(this.text, {super.key, this.style});

  final String text;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    return Text(text,
        style: (style ?? Theme.of(context).textTheme.titleMedium)?.copyWith(
          fontFeatures: const [FontFeature.tabularFigures()],
          fontWeight: FontWeight.w800,
        ));
  }
}

/// Premium stat card with icon tile, label, value + delta.
class StatCard extends StatelessWidget {
  const StatCard({super.key, required this.label,
    required this.value, this.icon, this.tint, this.delta, this.deltaUp});

  final String label;
  final String value;
  final IconData? icon;
  final Color? tint;
  final String? delta;
  final bool? deltaUp;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final t = tint ?? theme.colorScheme.primary;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(children: [
          if (icon != null)
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: t.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: t, size: 22),
            ),
          if (icon != null) const SizedBox(width: 12),
          Expanded(
            child: Column(
                crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(label.toUpperCase(), style: theme.textTheme.labelSmall),
              const SizedBox(height: 4),
              Text(value,
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontFeatures: const [FontFeature.tabularFigures()],
                  )),
              if (delta != null)
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Text(delta!,
                      style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: (deltaUp ?? true)
                              ? BizStayColors.successDark
                              : BizStayColors.errorDark)),
                ),
            ]),
          ),
        ]),
      ),
    );
  }
}

/// Section header: "TITLE" + optional trailing action.
class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.action});

  final String title;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
      child: Row(children: [
        Expanded(
          child: Text(title.toUpperCase(),
              style: Theme.of(context).textTheme.labelSmall),
        ),
        if (action != null) action!,
      ]),
    );
  }
}

/// Gradient hero header (navy gradient) for dashboards.
class HeroHeader extends StatelessWidget {
  const HeroHeader({super.key, required this.title,
    this.subtitle, this.trailing, this.leading});

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [BizStayColors.navy900, BizStayColors.navy700],
        ),
        borderRadius: BorderRadius.all(Radius.circular(20)),
      ),
      child: Row(children: [
        if (leading != null) ...[leading!, const SizedBox(width: 12)],
        Expanded(
          child: Column(
              crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title,
                style: const TextStyle(fontSize: 18,
                    fontWeight: FontWeight.w800, color: Colors.white)),
            if (subtitle != null)
              Text(subtitle!,
                  style: const TextStyle(
                      fontSize: 13, color: Colors.white70)),
          ]),
        ),
        if (trailing != null) trailing!,
      ]),
    );
  }
}

/// Empty state with icon + message + CTA.
class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon,
    required this.message, this.action});

  final IconData icon;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainer,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 34,
                color: theme.colorScheme.onSurfaceVariant),
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium),
          if (action != null) ...[
            const SizedBox(height: 14),
            action!,
          ],
        ]),
      ),
    );
  }
}

/// Error box with retry.
class ErrorState extends StatelessWidget {
  const ErrorState(this.message, {super.key, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      icon: Icons.cloud_off_outlined,
      message: message,
      action: onRetry == null
          ? null
          : FilledButton(onPressed: onRetry, child: const Text('Retry')),
    );
  }
}
