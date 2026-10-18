import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api_service.dart';
import '../theme/bizstay_colors.dart';
import '../widgets/biz.dart';
import '../widgets/bizstay_ui.dart';

class ManagerToday extends StatefulWidget {
  const ManagerToday(
      {super.key,
      required this.onLogout,
      required this.name,
      this.onGoTab});

  final VoidCallback onLogout;
  final String name;
  final void Function(int tab)? onGoTab;

  @override
  State<ManagerToday> createState() => _ManagerTodayState();
}

class _ManagerTodayState extends State<ManagerToday> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.dashboard();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            final msg = snap.error is ApiException
                ? (snap.error! as ApiException).message
                : 'Could not load dashboard.';
            return Biz.errorBox(msg,
                onRetry: () =>
                    setState(() => _future = ApiService.dashboard()));
          }
          final d = snap.data ?? {};
          final prop = (d['property'] as Map<String, dynamic>?) ?? {};
          final beds = (d['beds'] as Map<String, dynamic>?) ?? {};
          final dues = (d['dues'] as Map<String, dynamic>?) ?? {};
          final ops = (d['ops'] as Map<String, dynamic>?) ?? {};
          final pnl = (d['pnl'] as Map<String, dynamic>?) ?? {};
          final stays = (d['stays'] as Map<String, dynamic>?) ?? {};
          final bedsTotal = (beds['total'] as num?)?.toInt() ?? 0;
          final bedsOcc = (beds['occupied'] as num?)?.toInt() ?? 0;
          final bedsFree = (beds['free'] as num?)?.toInt() ?? 0;
          final occPct = (prop['occupancy_percent'] as num?)?.toDouble() ?? 0;
          final outstanding = ApiService.numOf(dues['outstanding']);
          final overdueCt = (dues['overdue_invoices'] as num?)?.toInt() ?? 0;
          final collected = ApiService.numOf(pnl['collected']);
          final expenses = ApiService.numOf(pnl['expenses']);
          final profit = ApiService.numOf(pnl['profit']);
          final openComplaints =
              (ops['open_complaints'] as num?)?.toInt() ?? 0;
          final openLeads = (ops['open_inquiries'] as num?)?.toInt() ?? 0;
          final liveStays = (stays['live'] as num?)?.toInt() ?? 0;
          return RefreshIndicator(
            onRefresh: () async {
              final v = await ApiService.dashboard();
              setState(() => _future = Future.value(v));
            },
            child: CustomScrollView(slivers: [
              SliverToBoxAdapter(
                child: _DashboardHeader(
                  name: widget.name,
                  property: '${prop['name'] ?? 'BizStay'}',
                  locality:
                      '${prop['locality'] ?? ''} ${prop['city'] ?? ''}'.trim(),
                  occPct: occPct,
                  bedsOcc: bedsOcc,
                  bedsTotal: bedsTotal,
                  bedsFree: bedsFree,
                  outstanding: outstanding,
                  overdueCt: overdueCt,
                  onLogout: widget.onLogout,
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
                sliver: SliverList.list(children: [
                  _MoneyRow(
                      collected: collected,
                      expenses: expenses,
                      profit: profit),
                  SectionHeader('Attention needed',
                      action: overdueCt > 0
                          ? CountPill('$overdueCt overdue')
                          : null),
                  _AttentionRow(
                    openComplaints: openComplaints,
                    openLeads: openLeads,
                    overdueCt: overdueCt,
                    liveStays: liveStays,
                    onGoTab: widget.onGoTab,
                  ),
                  SectionHeader('Quick actions'),
                  _QuickGrid(onGoTab: widget.onGoTab),
                  const SizedBox(height: 16),
                  Text('Signed in as ${widget.name}',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                          color: Colors.black38, fontSize: 12)),
                ]),
              ),
            ]),
          );
        },
      ),
    );
  }
}


/// OYO-style navy dashboard header with occupancy ring + dues banner.
class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader(
      {required this.name,
      required this.property,
      required this.locality,
      required this.occPct,
      required this.bedsOcc,
      required this.bedsTotal,
      required this.bedsFree,
      required this.outstanding,
      required this.overdueCt,
      required this.onLogout});

  final String name;
  final String property;
  final String locality;
  final double occPct;
  final int bedsOcc;
  final int bedsTotal;
  final int bedsFree;
  final double outstanding;
  final int overdueCt;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour;
    final greet =
        hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    final first = name.split(' ').firstWhere((e) => e.isNotEmpty,
        orElse: () => name);
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [BizStayColors.navy900, Color(0xFF27365A)]),
        borderRadius:
            BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 20),
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                    color: BizStayColors.gold500,
                    borderRadius: BorderRadius.circular(14)),
                alignment: Alignment.center,
                child: Text(
                    first.isEmpty ? 'B' : first[0].toUpperCase(),
                    style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                        color: BizStayColors.navy900)),
              ),
              const SizedBox(width: 12),
              Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                    Text('$greet, $first',
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 17,
                            fontWeight: FontWeight.w800)),
                    Text('$property • $locality',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: Colors.white70, fontSize: 12)),
                  ])),
              IconButton(
                  onPressed: onLogout,
                  tooltip: 'Sign out',
                  icon: const Icon(Icons.logout_rounded,
                      color: Colors.white70)),
            ]),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18)),
              child: Row(children: [
                _OccupancyRing(pct: occPct),
                const SizedBox(width: 14),
                Expanded(
                    child: Column(
                        crossAxisAlignment:
                            CrossAxisAlignment.start,
                        children: [
                      const Text('OCCUPANCY',
                          style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 1,
                              color: BizStayColors.slate500)),
                      Text('$bedsOcc/$bedsTotal beds',
                          style: const TextStyle(
                              fontSize: 19,
                              fontWeight: FontWeight.w900,
                              color: BizStayColors.navy900)),
                      const SizedBox(height: 6),
                      ClipRRect(
                        borderRadius:
                            BorderRadius.circular(99),
                        child: LinearProgressIndicator(
                            value:
                                (occPct / 100).clamp(0, 1),
                            minHeight: 7,
                            backgroundColor:
                                BizStayColors.slate200,
                            valueColor:
                                const AlwaysStoppedAnimation(
                                    BizStayColors.gold500)),
                      ),
                      const SizedBox(height: 6),
                      Text('$bedsFree beds free',
                          style: const TextStyle(
                              fontSize: 12,
                              color:
                                  BizStayColors.slate600)),
                    ])),
              ]),
            ),
            const SizedBox(height: 10),
            _DuesBanner(
                outstanding: outstanding,
                overdueCt: overdueCt),
          ]),
        ),
      ),
    );
  }
}

class _OccupancyRing extends StatelessWidget {
  const _OccupancyRing({required this.pct});
  final double pct;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 74,
      height: 74,
      child: Stack(alignment: Alignment.center, children: [
        SizedBox(
          width: 74,
          height: 74,
          child: CircularProgressIndicator(
              value: (pct / 100).clamp(0, 1),
              strokeWidth: 8,
              backgroundColor: BizStayColors.slate200,
              valueColor: const AlwaysStoppedAnimation(
                  BizStayColors.navy900)),
        ),
        Text('${pct.toStringAsFixed(0)}%',
            style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w900,
                color: BizStayColors.navy900)),
      ]),
    );
  }
}

class _DuesBanner extends StatelessWidget {
  const _DuesBanner(
      {required this.outstanding, required this.overdueCt});
  final double outstanding;
  final int overdueCt;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding:
          const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
          color: overdueCt > 0
              ? BizStayColors.errorBg
              : BizStayColors.gold100,
          borderRadius: BorderRadius.circular(16)),
      child: Row(children: [
        Icon(
            overdueCt > 0
                ? Icons.warning_amber_rounded
                : Icons.check_circle_outline,
            color: overdueCt > 0
                ? BizStayColors.errorDark
                : BizStayColors.gold600),
        const SizedBox(width: 10),
        Expanded(
            child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
              Text(Biz.money(outstanding),
                  style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                      color: BizStayColors.navy900)),
              Text(
                  overdueCt > 0
                      ? '$overdueCt overdue bills need action'
                      : 'All dues clear. Nice work.',
                  style: const TextStyle(
                      fontSize: 12,
                      color: BizStayColors.slate600)),
            ])),
      ]),
    );
  }
}


/// Collected / Expenses / Profit strip.
class _MoneyRow extends StatelessWidget {
  const _MoneyRow(
      {required this.collected,
      required this.expenses,
      required this.profit});
  final double collected;
  final double expenses;
  final double profit;

  @override
  Widget build(BuildContext context) {
    Widget cell(String label, String value, Color color) {
      return Expanded(
        child: Padding(
          padding:
              const EdgeInsets.symmetric(vertical: 8),
          child: Column(children: [
            Text(label.toUpperCase(),
                style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.8,
                    color: BizStayColors.slate500)),
            Text(value,
                maxLines: 1,
                style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: color)),
          ]),
        ),
      );
    }

    return Card(
      child: Padding(
        padding:
            const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          cell('Collected', Biz.money(collected),
              BizStayColors.successDark),
          Container(width: 1, height: 40,
              color: BizStayColors.slate200),
          cell('Expenses', Biz.money(expenses),
              BizStayColors.slate600),
          Container(width: 1, height: 40,
              color: BizStayColors.slate200),
          cell('Profit', Biz.money(profit),
              BizStayColors.navy900),
        ]),
      ),
    );
  }
}

/// Attention tiles jumping to the right tab.
class _AttentionRow extends StatelessWidget {
  const _AttentionRow(
      {required this.openComplaints,
      required this.openLeads,
      required this.overdueCt,
      required this.liveStays,
      this.onGoTab});
  final int openComplaints;
  final int openLeads;
  final int overdueCt;
  final int liveStays;
  final void Function(int tab)? onGoTab;

  @override
  Widget build(BuildContext context) {
    Widget tile(String label, String value, IconData icon,
        Color tint, Color bg, int tab) {
      return Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => onGoTab?.call(tab),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
                borderRadius:
                    BorderRadius.circular(16),
                border: Border.all(
                    color: BizStayColors.slate200)),
            child: Row(children: [
              Container(
                  padding:
                      const EdgeInsets.all(9),
                  decoration: BoxDecoration(
                      color: bg,
                      borderRadius:
                          BorderRadius.circular(12)),
                  child: Icon(icon,
                      color: tint, size: 20)),
              const SizedBox(width: 10),
              Expanded(
                  child: Column(
                      mainAxisAlignment:
                          MainAxisAlignment.center,
                      crossAxisAlignment:
                          CrossAxisAlignment.start,
                      children: [
                    Text(value,
                        style: const TextStyle(
                            fontSize: 20,
                            fontWeight:
                                FontWeight.w900,
                            color:
                                BizStayColors.navy900)),
                    Text(label,
                        style: const TextStyle(
                            fontSize: 12,
                            color: BizStayColors
                                .slate600)),
                  ])),
            ]),
          ),
        ),
      );
    }

    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 1.9,
      children: [
        tile('Overdue', '$overdueCt',
            Icons.schedule_rounded,
            BizStayColors.errorDark,
            BizStayColors.errorBg, 3),
        tile('Complaints', '$openComplaints',
            Icons.handyman_outlined,
            BizStayColors.warning,
            BizStayColors.warningBg, 4),
        tile('Live stays', '$liveStays',
            Icons.bed_outlined,
            BizStayColors.navy900,
            BizStayColors.slate100, 1),
        tile('New leads', '$openLeads',
            Icons.person_add_alt_outlined,
            BizStayColors.info,
            BizStayColors.infoBg, 4),
      ],
    );
  }
}


/// Quick actions grid.
class _QuickGrid extends StatelessWidget {
  const _QuickGrid({this.onGoTab});
  final void Function(int tab)? onGoTab;

  @override
  Widget build(BuildContext context) {
    Widget btn(IconData icon, String label, int tab) {
      return InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => onGoTab?.call(tab),
        child: Padding(
          padding: const EdgeInsets.symmetric(
              horizontal: 10, vertical: 8),
          child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                    padding:
                        const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                        color: BizStayColors.navy900
                            .withValues(alpha: 0.06),
                        borderRadius:
                            BorderRadius.circular(16)),
                    child: Icon(icon,
                        color:
                            BizStayColors.navy900)),
                const SizedBox(height: 6),
                Text(label,
                    style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color:
                            BizStayColors.slate700)),
              ]),
        ),
      );
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Row(
            mainAxisAlignment:
                MainAxisAlignment.spaceAround,
            children: [
              btn(Icons.payments_outlined,
                  'Collect', 3),
              btn(Icons.bed_outlined,
                  'Rooms', 1),
              btn(Icons.people_outline,
                  'Residents', 2),
              btn(Icons.handyman_outlined,
                  'Ops', 4),
            ]),
      ),
    );
  }
}

/// Small sheet used by tenants to copy the property UPI id.
class UpiCard extends StatelessWidget {
  const UpiCard({super.key, required this.upiId, required this.name});

  final String upiId;
  final String name;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: const Color(0xFF0F766E),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
            crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Pay via UPI',
              style: TextStyle(color: Colors.white70, fontSize: 12)),
          const SizedBox(height: 4),
          SelectableText(upiId,
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.w800)),
          Text(name, style: const TextStyle(color: Colors.white70)),
          const SizedBox(height: 8),
          FilledButton.tonal(
              onPressed: () {
                Clipboard.setData(ClipboardData(text: upiId));
                ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('UPI ID copied')));
              },
              child: const Text('Copy UPI ID')),
        ]),
      ),
    );
  }
}
