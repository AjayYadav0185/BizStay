import 'package:flutter/material.dart';

import '../api_service.dart';
import '../widgets/biz.dart';

const collectMethods = ['upi', 'cash', 'netbanking', 'card', 'cheque'];

class ManagerBilling extends StatefulWidget {
  const ManagerBilling({super.key});

  @override
  State<ManagerBilling> createState() => _ManagerBillingState();
}

class _ManagerBillingState extends State<ManagerBilling> {
  String _filter = 'outstanding';
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.invoices(status: _filter);
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
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Row(
              children: ['outstanding', 'overdue', 'paid', '']
                  .map((f) => Padding(
                        padding: const EdgeInsets.only(right: 8),
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
              if (snap.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snap.hasError) {
                return Biz.errorBox('Could not load invoices.',
                    onRetry: _reload);
              }
              final rows = ApiService.pageItems(snap.data);
              if (rows.isEmpty) {
                return const Center(
                    child: Text('Nothing here. All clear!'));
              }
              final total = rows.fold<double>(
                  0, (s, e) => s + ApiService.numOf(e['balance']));
              return Column(children: [
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Biz.statCard('Showing total balance',
                      Biz.money(total, paise: true),
                      icon: Icons.account_balance_wallet),
                ),
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                    itemCount: rows.length,
                    itemBuilder: (context, i) {
                      final v = rows[i];
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          title: Text(
                              '${v['invoice_number'] ?? ''} • ${v['guest'] ?? ''}'),
                          subtitle: Text(
                              '${v['cycle'] ?? ''} • Due ${v['due_date'] ?? ''}'),
                          trailing: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(Biz.money(v['balance'] ?? 0, paise: true),
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w800)),
                                Biz.chip('${v['status'] ?? ''}'),
                              ]),
                          onTap: () => _collectSheet(v),
                        ),
                      );
                    },
                  ),
                ),
              ]);
            },
          ),
        ),
      ]),
    );
  }
}
