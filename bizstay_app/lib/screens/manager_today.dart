import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api_service.dart';
import '../widgets/biz.dart';

class ManagerToday extends StatefulWidget {
  const ManagerToday(
      {super.key, required this.onLogout, required this.name});

  final VoidCallback onLogout;
  final String name;

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
      appBar: AppBar(title: const Text('Today'), actions: [
        IconButton(
            onPressed: () => setState(() => _future = ApiService.dashboard()),
            icon: const Icon(Icons.refresh)),
        IconButton(
            onPressed: widget.onLogout, icon: const Icon(Icons.logout)),
      ]),
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
          return RefreshIndicator(
            onRefresh: () async {
              final v = await ApiService.dashboard();
              setState(() => _future = Future.value(v));
            },
            child: ListView(padding: const EdgeInsets.all(14), children: [
              Card(
                  child: ListTile(
                      leading: const Icon(Icons.apartment,
                          color: Biz.primary, size: 34),
                      title: Text('${prop['name'] ?? 'BizStay'}',
                          style: const TextStyle(
                              fontWeight: FontWeight.w800)),
                      subtitle: Text(
                          '${prop['locality'] ?? ''} ${prop['city'] ?? ''}')),
                  ),
              const SizedBox(height: 8),
              Biz.statCard(
                  'Occupancy',
                  '${prop['occupancy_percent'] ?? 0}%  •  ${beds['occupied'] ?? 0}/${beds['total'] ?? 0} beds',
                  icon: Icons.bed),
              Row(children: [
                Expanded(
                    child: Biz.statCard('Outstanding',
                        Biz.money(dues['outstanding'] ?? 0),
                        icon: Icons.warning_amber_rounded,
                        tint: Colors.red)),
                const SizedBox(width: 8),
                Expanded(
                    child: Biz.statCard('Overdue bills',
                        '${dues['overdue_invoices'] ?? 0}',
                        icon: Icons.schedule, tint: Colors.orange)),
              ]),
              Row(children: [
                Expanded(
                    child: Biz.statCard('Collected (month)',
                        Biz.money(pnl['collected'] ?? 0),
                        icon: Icons.trending_up, tint: Colors.green)),
                const SizedBox(width: 8),
                Expanded(
                    child: Biz.statCard(
                        'Profit (month)', Biz.money(pnl['profit'] ?? 0),
                        icon: Icons.account_balance_wallet,
                        tint: Colors.teal)),
              ]),
              Biz.statCard('Open complaints',
                  '${ops['open_complaints'] ?? 0}',
                  icon: Icons.handyman),
              Biz.statCard(
                  'Open leads', '${ops['open_inquiries'] ?? 0}',
                  icon: Icons.person_add_alt),
              Text('Signed in as ${widget.name}',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                      color: Colors.black45, fontSize: 12)),
            ]),
          );
        },
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
