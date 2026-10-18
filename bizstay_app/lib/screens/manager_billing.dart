import 'package:flutter/material.dart';

import '../api_service.dart';
import '../theme/bizstay_colors.dart';
import '../widgets/biz.dart';
import '../widgets/bizstay_ui.dart';

const collectMethods = ['upi', 'cash', 'netbanking', 'card', 'cheque'];

class ManagerBilling extends StatefulWidget {
  const ManagerBilling({super.key});

  @override
  State<ManagerBilling> createState() => _ManagerBillingState();
}

class _ManagerBillingState extends State<ManagerBilling> {
  String _filter = 'outstanding';
  final _search = TextEditingController();
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.invoices(status: _filter);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> _visible(
      List<Map<String, dynamic>> rows) {
    final q = _search.text.trim().toLowerCase();
    if (q.isEmpty) return rows;
    return rows.where((v) {
      final hay =
          '${v['invoice_number']} ${v['guest']} ${v['cycle']}'
              .toLowerCase();
      return hay.contains(q);
    }).toList();
  }

  void _reload() {
    setState(() => _future = ApiService.invoices(status: _filter));
  }

  Future<void> _collectSheet(Map<String, dynamic> inv) async {
    final amount = TextEditingController(
        text: ApiService.numOf(inv['balance']).toStringAsFixed(0));
    final txn = TextEditingController();
    String method = 'upi';
    bool busy = false;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheet) => Padding(
          padding: EdgeInsets.only(
              left: 20,
              right: 20,
              top: 20,
              bottom: MediaQuery.of(ctx).viewInsets.bottom + 20),
          child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Collect — ${inv['invoice_number'] ?? ''}',
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 16)),
                Text(
                    '${inv['guest'] ?? ''} • Bal ${Biz.money(inv['balance'], paise: true)}',
                    style: const TextStyle(color: Colors.black54)),
                const SizedBox(height: 12),
                TextField(
                    controller: amount,
                    keyboardType:
                        const TextInputType.numberWithOptions(decimal: true),
                    decoration: const InputDecoration(
                        labelText: 'Amount (Rs)',
                        border: OutlineInputBorder())),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                    initialValue: method,
                    decoration: const InputDecoration(
                        labelText: 'Method', border: OutlineInputBorder()),
                    items: collectMethods
                        .map((m) =>
                            DropdownMenuItem(value: m, child: Text(m)))
                        .toList(),
                    onChanged: (v) => setSheet(() => method = v ?? 'upi')),
                const SizedBox(height: 10),
                TextField(
                    controller: txn,
                    decoration: const InputDecoration(
                        labelText: 'UTR / reference (optional)',
                        border: OutlineInputBorder())),
                const SizedBox(height: 14),
                FilledButton(
                    onPressed: busy
                        ? null
                        : () async {
                            setSheet(() => busy = true);
                            try {
                              await ApiService.collect(
                                  invoiceId: (inv['id'] as num).toInt(),
                                  amount:
                                      double.tryParse(amount.text) ?? 0,
                                  method: method,
                                  transactionId: txn.text.trim());
                              if (ctx.mounted) Navigator.pop(ctx, true);
                            } on ApiException catch (e) {
                              setSheet(() => busy = false);
                              if (!ctx.mounted) return;
                              ScaffoldMessenger.of(ctx).showSnackBar(
                                  SnackBar(content: Text(e.message)));
                            }
                          },
                    child: Text(busy ? 'Saving…' : 'Record payment')),
              ]),
        ),
      ),
    );
    if (ok == true) {
      _reload();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Payment recorded.')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(title: const Text('Billing')),
      body: Column(children: [
        Padding(
          padding:
              const EdgeInsets.fromLTRB(12, 12, 12, 0),
          child: SearchField(
              controller: _search,
              hint:
                  'Search invoice, guest, month…',
              onSearch: () =>
                  setState(() {})),
        ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding:
              const EdgeInsets.fromLTRB(12, 10, 12, 2),
          child: Row(
              children: ['outstanding', 'overdue', 'paid', '']
                  .map((f) => Padding(
                        padding:
                            const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                            label: Text(f.isEmpty ? 'all' : f),
                            selected: _filter == f,
                            onSelected: (_) {
                              _filter = f;
                              _reload();
                            }),
                      ))
                  .toList()),
        ),
        Expanded(
          child: FutureBuilder<Map<String, dynamic>>(
            future: _future,
            builder: (context, snap) {
              if (snap.connectionState ==
                  ConnectionState.waiting) {
                return const Center(
                    child: CircularProgressIndicator());
              }
              if (snap.hasError) {
                return Biz.errorBox(
                    'Could not load invoices.',
                    onRetry: _reload);
              }
              final rows = _visible(
                  ApiService.pageItems(snap.data));
              if (rows.isEmpty) {
                return const EmptyState(
                    icon: Icons.receipt_long_outlined,
                    message: 'Nothing here. All clear!');
              }
              final total = rows.fold<double>(0,
                  (s, e) => s + ApiService.numOf(e['balance']));
              return Column(children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                      12, 8, 12, 8),
                  child: _TotalStrip(
                      total: total, count: rows.length),
                ),
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(
                        12, 0, 12, 90),
                    itemCount: rows.length,
                    itemBuilder: (context, i) {
                      final v = rows[i];
                      return _InvoiceCard(
                          inv: v,
                          onCollect: () => _collectSheet(v));
                    },
                  ),
                ),
              ]);
            },
          ),
        ),
      ]),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () =>
            showAdminOnlySheet(context, 'Invoices'),
        icon: const Icon(Icons.add),
        label: const Text('New bill'),
      ),
    );
  }
}

// MARKER_BILLWIDGETS

/// Gold total strip with count.
class _TotalStrip extends StatelessWidget {
  const _TotalStrip({required this.total, required this.count});
  final double total;
  final int count;
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
          gradient: const LinearGradient(
              begin: Alignment.topLeft, end: Alignment.bottomRight,
              colors: [BizStayColors.navy900, Color(0xFF334155)]),
          borderRadius: BorderRadius.circular(16)),
      child: Row(children: [
        Expanded(
            child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
              Text('TOTAL • $count BILLS',
                  style: const TextStyle(fontSize: 10,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.8, color: Colors.white70)),
              Text(Biz.money(total, paise: true),
                  style: const TextStyle(fontSize: 22,
                      fontWeight: FontWeight.w900, color: Colors.white)),
            ])),
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
              color: BizStayColors.gold500,
              borderRadius: BorderRadius.circular(12)),
          child: const Icon(Icons.account_balance_wallet,
              color: BizStayColors.navy900),
        ),
      ]),
    );
  }
}

/// Invoice row with Collect CTA.
class _InvoiceCard extends StatelessWidget {
  const _InvoiceCard({required this.inv, required this.onCollect});
  final Map<String, dynamic> inv;
  final VoidCallback onCollect;
  @override
  Widget build(BuildContext context) {
    final bal = ApiService.numOf(inv['balance']);
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(children: [
          Row(children: [
            Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  Text('${inv['invoice_number'] ?? ''}',
                      style: const TextStyle(
                          fontWeight: FontWeight.w900, fontSize: 15)),
                  Text('${inv['guest'] ?? ''} • ${inv['cycle'] ?? ''}',
                      maxLines: 1, overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          color: BizStayColors.slate600, fontSize: 12)),
                  Text('Due ${inv['due_date'] ?? ''}',
                      style: const TextStyle(
                          color: BizStayColors.slate500, fontSize: 12)),
                ])),
            Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  MoneyText(Biz.money(bal, paise: true)),
                  const SizedBox(height: 4),
                  StatusChip('${inv['status'] ?? ''}'),
                ]),
          ]),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
                onPressed: bal > 0 ? onCollect : null,
                icon: const Icon(Icons.payments_outlined, size: 18),
                label: Text(bal > 0
                    ? 'Collect ${Biz.money(bal, paise: true)}'
                    : 'Settled')),
          ),
        ]),
      ),
    );
  }
}
