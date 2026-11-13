import 'package:flutter/material.dart';

import 'package:flutter/services.dart';

import '../api_service.dart';
import '../links.dart';
import '../theme/bizstay_colors.dart';
import '../widgets/biz.dart';
import 'manager_today.dart';

class TenantShell extends StatefulWidget {
  const TenantShell(
      {super.key, required this.session, required this.onSignOut});

  final Session session;
  final VoidCallback onSignOut;

  @override
  State<TenantShell> createState() => _TenantShellState();
}

class _TenantShellState extends State<TenantShell> {
  int _tab = 0;

  Future<void> _logout() async {
    await ApiService.logout();
    widget.onSignOut();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _tab, children: [
        TenantStayTab(name: widget.session.name, onLogout: _logout),
        const TenantBillsTab(),
        const TenantHelpTab(),
      ]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: 'My stay'),
          NavigationDestination(
              icon: Icon(Icons.receipt_outlined),
              selectedIcon: Icon(Icons.receipt),
              label: 'Bills'),
          NavigationDestination(
              icon: Icon(Icons.help_outline),
              selectedIcon: Icon(Icons.help),
              label: 'Help'),
        ],
      ),
    );
  }
}

class TenantStayTab extends StatefulWidget {
  const TenantStayTab(
      {super.key, required this.name, required this.onLogout});

  final String name;
  final VoidCallback onLogout;

  @override
  State<TenantStayTab> createState() => _TenantStayTabState();
}

class _TenantStayTabState extends State<TenantStayTab> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.myStay();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(title: const Text('My stay'), actions: [
        IconButton(
            onPressed: () => setState(() => _future = ApiService.myStay()),
            icon: const Icon(Icons.refresh)),
        IconButton(onPressed: widget.onLogout, icon: const Icon(Icons.logout)),
      ]),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Biz.errorBox('Could not load your stay.',
                onRetry: () =>
                    setState(() => _future = ApiService.myStay()));
          }
          final d = snap.data ?? {};
          final guest = (d['guest'] as Map<String, dynamic>?) ?? {};
          final prop = (d['property'] as Map<String, dynamic>?) ?? {};
          final due = (d['due'] as Map<String, dynamic>?) ?? {};
          final payments = (d['payments'] as List?) ?? [];
          return RefreshIndicator(
            onRefresh: () async {
              final v = await ApiService.myStay();
              setState(() => _future = Future.value(v));
            },
            child: ListView(padding: const EdgeInsets.all(14), children: [
              Text('Hi, ${guest['full_name'] ?? widget.name}',
                  style: const TextStyle(
                      fontSize: 20, fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              Biz.statCard(
                  'Bed', '${d['bed'] ?? '—'} • Room ${d['room'] ?? '—'}',
                  icon: Icons.bed),
              Biz.statCard('Outstanding',
                  Biz.money(d['outstanding'] ?? 0, paise: true),
                  icon: Icons.warning_amber_rounded, tint: Colors.red),
              if (due.isNotEmpty &&
                  ApiService.numOf(due['balance']) > 0)
                PayCard(
                  invoiceId: (due['id'] as num?)?.toInt(),
                  invoiceNumber: '${due['invoice_number'] ?? ''}',
                  amount: ApiService.numOf(due['balance']),
                  dueDate: '${due['due_date'] ?? ''}',
                  upiUri: due['upi_uri'] as String?,
                  payLink: due['pay_link'] as String?,
                  onlinePayments: d['online_payments'] == true,
                  upiId: '${prop['upi_id'] ?? ''}',
                )
              else if ('${prop['upi_id'] ?? ''}'.isNotEmpty)
                UpiCard(
                    upiId: '${prop['upi_id']}',
                    name: '${prop['name'] ?? ''}'),
              if ('${prop['whatsapp'] ?? ''}'.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: OutlinedButton.icon(
                    onPressed: () => Links.open(
                        context,
                        Links.whatsapp('${prop['whatsapp']}',
                            'Hi, this is ${guest['full_name'] ?? widget.name} (Bed ${d['bed'] ?? ''}).'),
                        failMessage: 'WhatsApp is not installed.'),
                    icon: const Icon(Icons.chat_outlined,
                        color: Color(0xFF25D366)),
                    label: const Text('Message the PG office on WhatsApp'),
                  ),
                ),
              if ('${prop['check_in_time'] ?? ''}'.isNotEmpty)
                Text(
                    'Check-in ${prop['check_in_time']} • Check-out ${prop['check_out_time'] ?? ''}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.black54)),
              const SizedBox(height: 8),
              const Text('Recent payments',
                  style: TextStyle(fontWeight: FontWeight.w800)),
              ...payments.map<Widget>((p) {
                final m = (p as Map).cast<String, dynamic>();
                return Card(
                  child: ListTile(
                    leading: const Icon(Icons.check_circle,
                        color: Colors.green),
                    title: Text(Biz.money(m['amount'] ?? 0, paise: true)),
                    subtitle: Text(
                        '${m['payment_method'] ?? ''} • ${m['paid_on'] ?? ''}'),
                  ),
                );
              }),
            ]),
          );
        },
      ),
    );
  }
}

class TenantBillsTab extends StatefulWidget {
  const TenantBillsTab({super.key});

  @override
  State<TenantBillsTab> createState() => _TenantBillsTabState();
}

class _TenantBillsTabState extends State<TenantBillsTab> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.invoices();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(title: const Text('Bills')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Biz.errorBox('Could not load bills.',
                onRetry: () =>
                    setState(() => _future = ApiService.invoices()));
          }
          final rows = ApiService.pageItems(snap.data);
          if (rows.isEmpty) return const Center(child: Text('No bills yet.'));
          return RefreshIndicator(
            onRefresh: () async {
              final v = await ApiService.invoices();
              setState(() => _future = Future.value(v));
            },
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: rows.length,
              itemBuilder: (context, i) {
                final v = rows[i];
                final balance = ApiService.numOf(v['balance']);
                final upi = '${v['upi_uri'] ?? ''}';
                final link = '${v['pay_link'] ?? ''}';
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    onTap: balance > 0 && (upi.isNotEmpty || link.isNotEmpty)
                        ? () => Links.open(context, link.isNotEmpty ? link : upi,
                            failMessage: 'No UPI app found on this phone.')
                        : null,
                    title: Text(
                        '${v['invoice_number'] ?? ''} • ${v['cycle'] ?? ''}'),
                    subtitle: Text(
                        'Rent ${Biz.money(v['rent'] ?? 0)} + Utility ${Biz.money(v['utility'] ?? 0)} + GST ${Biz.money(v['gst_amount'] ?? 0, paise: true)} • Due ${v['due_date'] ?? ''}'),
                    trailing: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(Biz.money(v['balance'] ?? 0, paise: true),
                              style:
                                  const TextStyle(fontWeight: FontWeight.w800)),
                          Biz.chip('${v['status'] ?? ''}'),
                          if (balance > 0 && (upi.isNotEmpty || link.isNotEmpty))
                            const Text('Tap to pay',
                                style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                    color: BizStayColors.info)),
                        ]),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class TenantHelpTab extends StatefulWidget {
  const TenantHelpTab({super.key});

  @override
  State<TenantHelpTab> createState() => _TenantHelpTabState();
}

class _TenantHelpTabState extends State<TenantHelpTab> {
  late Future<Map<String, dynamic>> _future;
  final _title = TextEditingController();
  final _desc = TextEditingController();

  @override
  void initState() {
    super.initState();
    _future = ApiService.complaints();
  }

  @override
  void dispose() {
    _title.dispose();
    _desc.dispose();
    super.dispose();
  }

  Future<void> _raise() async {
    if (_title.text.trim().isEmpty) return;
    try {
      await ApiService.raiseComplaint(
          title: _title.text.trim(), description: _desc.text.trim());
      _title.clear();
      _desc.clear();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Request sent.')));
      setState(() => _future = ApiService.complaints());
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(title: const Text('Help')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          final rows = snap.hasData
              ? ApiService.pageItems(snap.data)
              : <Map<String, dynamic>>[];
          return ListView(padding: const EdgeInsets.all(14), children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Raise a request',
                          style: TextStyle(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 8),
                      TextField(
                          controller: _title,
                          decoration: const InputDecoration(
                              labelText: 'What is wrong?',
                              border: OutlineInputBorder())),
                      const SizedBox(height: 8),
                      TextField(
                          controller: _desc,
                          maxLines: 2,
                          decoration: const InputDecoration(
                              labelText: 'Details (optional)',
                              border: OutlineInputBorder())),
                      const SizedBox(height: 8),
                      FilledButton(
                          onPressed: _raise, child: const Text('Send')),
                    ]),
              ),
            ),
            const SizedBox(height: 8),
            const Text('My requests',
                style: TextStyle(fontWeight: FontWeight.w800)),
            if (snap.connectionState == ConnectionState.waiting)
              const Padding(
                  padding: EdgeInsets.all(20),
                  child: Center(child: CircularProgressIndicator())),
            ...rows.map((c) => Card(
                  child: ListTile(
                    title: Text('${c['title'] ?? ''}'),
                    subtitle: Text('${c['created'] ?? ''}'),
                    trailing: Biz.chip('${c['status'] ?? ''}'),
                  ),
                )),
          ]);
        },
      ),
    );
  }
}

/// Pay the current bill: Razorpay link when online payments are on,
/// otherwise any UPI app opened with payee + amount pre-filled.
class PayCard extends StatefulWidget {
  const PayCard({
    super.key,
    required this.invoiceId,
    required this.invoiceNumber,
    required this.amount,
    required this.dueDate,
    required this.upiUri,
    required this.payLink,
    required this.onlinePayments,
    required this.upiId,
  });

  final int? invoiceId;
  final String invoiceNumber;
  final double amount;
  final String dueDate;
  final String? upiUri;
  final String? payLink;
  final bool onlinePayments;
  final String upiId;

  @override
  State<PayCard> createState() => _PayCardState();
}

class _PayCardState extends State<PayCard> {
  bool _busy = false;

  Future<void> _payOnline() async {
    setState(() => _busy = true);
    try {
      var url = widget.payLink ?? '';
      if (url.isEmpty && widget.invoiceId != null) {
        url = await ApiService.payLink(widget.invoiceId!);
      }
      if (!mounted) return;
      await Links.open(context, url,
          failMessage: 'Could not open the payment page.');
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _payUpi() async {
    final opened = await Links.open(context, widget.upiUri ?? '',
        failMessage: 'No UPI app found. Copy the UPI ID and pay manually.');
    if (opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          duration: Duration(seconds: 6),
          content: Text(
              'After paying, share the UTR / screenshot with the office so it is marked paid.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasUpi = (widget.upiUri ?? '').isNotEmpty;
    return Card(
      color: BizStayColors.navy900,
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Rent due • ${widget.invoiceNumber}',
              style: const TextStyle(color: Colors.white70, fontSize: 12)),
          const SizedBox(height: 4),
          Text(Biz.money(widget.amount, paise: true),
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 28,
                  fontWeight: FontWeight.w900)),
          Text('Due ${widget.dueDate}',
              style: const TextStyle(color: Colors.white70)),
          const SizedBox(height: 12),
          if (widget.onlinePayments)
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                style: FilledButton.styleFrom(
                    backgroundColor: BizStayColors.gold500,
                    foregroundColor: BizStayColors.navy900),
                onPressed: _busy ? null : _payOnline,
                icon: const Icon(Icons.lock_outline),
                label: Text(_busy ? 'Opening…' : 'Pay online (UPI / card)'),
              ),
            ),
          if (hasUpi) ...[
            if (widget.onlinePayments) const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: widget.onlinePayments
                  ? OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.white,
                          side: const BorderSide(color: Colors.white38)),
                      onPressed: _payUpi,
                      icon: const Icon(Icons.qr_code_2),
                      label: const Text('Pay with UPI app'))
                  : FilledButton.icon(
                      style: FilledButton.styleFrom(
                          backgroundColor: BizStayColors.gold500,
                          foregroundColor: BizStayColors.navy900),
                      onPressed: _payUpi,
                      icon: const Icon(Icons.qr_code_2),
                      label: const Text('Pay with GPay / PhonePe / Paytm')),
            ),
          ],
          if (widget.upiId.isNotEmpty)
            TextButton.icon(
              style: TextButton.styleFrom(foregroundColor: Colors.white70),
              onPressed: () {
                Clipboard.setData(ClipboardData(text: widget.upiId));
                ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('UPI ID copied')));
              },
              icon: const Icon(Icons.copy, size: 16),
              label: Text('UPI ID: ${widget.upiId}'),
            ),
        ]),
      ),
    );
  }
}
