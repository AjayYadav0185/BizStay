import 'package:flutter/material.dart';

import '../api_service.dart';
import '../widgets/biz.dart';

class ManagerOps extends StatefulWidget {
  const ManagerOps({super.key});

  @override
  State<ManagerOps> createState() => _ManagerOpsState();
}

class _ManagerOpsState extends State<ManagerOps>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 2, vsync: this);

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(
          title: const Text('Ops'),
          bottom: TabBar(controller: _tabs, tabs: const [
            Tab(text: 'Complaints'),
            Tab(text: 'Leads'),
          ])),
      body: TabBarView(controller: _tabs, children: const [
        _ComplaintList(),
        _LeadList(),
      ]),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          final ok = await showDialog<bool>(
              context: context,
              builder: (ctx) => const ComplaintDialog());
          if (ok == true && context.mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Complaint logged.')));
            setState(() {});
          }
        },
        tooltip: 'Log complaint',
        child: const Icon(Icons.add),
      ),
    );
  }
}

class _ComplaintList extends StatefulWidget {
  const _ComplaintList();

  @override
  State<_ComplaintList> createState() => _ComplaintListState();
}

class _ComplaintListState extends State<_ComplaintList> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.complaints();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return Biz.errorBox('Could not load complaints.',
              onRetry: () =>
                  setState(() => _future = ApiService.complaints()));
        }
        final rows = ApiService.pageItems(snap.data);
        if (rows.isEmpty) {
          return const Center(child: Text('No complaints.'));
        }
        return RefreshIndicator(
          onRefresh: () async {
            final v = await ApiService.complaints();
            setState(() => _future = Future.value(v));
          },
          child: ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: rows.length,
            itemBuilder: (context, i) {
              final c = rows[i];
              final breached = c['sla_breached'] == true;
              return Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: Icon(Icons.handyman,
                      color: Biz.statusColor('${c['status']}')),
                  title: Text('${c['title'] ?? ''}'),
                  subtitle: Text(
                      '${c['guest'] ?? '—'} • Room ${c['room'] ?? '—'}'),
                  trailing: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Biz.chip('${c['status'] ?? ''}'),
                        if (breached)
                          const Text('SLA breached',
                              style: TextStyle(
                                  color: Colors.red, fontSize: 11)),
                      ]),
                ),
              );
            },
          ),
        );
      },
    );
  }
}

class _LeadList extends StatefulWidget {
  const _LeadList();

  @override
  State<_LeadList> createState() => _LeadListState();
}

class _LeadListState extends State<_LeadList> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.inquiries();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return Biz.errorBox('Could not load leads.',
              onRetry: () =>
                  setState(() => _future = ApiService.inquiries()));
        }
        final rows = ApiService.pageItems(snap.data);
        if (rows.isEmpty) return const Center(child: Text('No leads.'));
        return ListView.builder(
          padding: const EdgeInsets.all(12),
          itemCount: rows.length,
          itemBuilder: (context, i) {
            final l = rows[i];
            return Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person)),
                title: Text('${l['name'] ?? ''} • ${l['phone'] ?? ''}'),
                subtitle: Text('${l['source'] ?? ''} • ${l['follow_up'] ?? ''}'),
                trailing: Biz.chip('${l['status'] ?? ''}'),
              ),
            );
          },
        );
      },
    );
  }
}

class ComplaintDialog extends StatefulWidget {
  const ComplaintDialog({super.key});

  @override
  State<ComplaintDialog> createState() => ComplaintDialogState();
}

class ComplaintDialogState extends State<ComplaintDialog> {
  final _title = TextEditingController();
  final _desc = TextEditingController();
  String _priority = 'medium';
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    _desc.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Log complaint'),
      content: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(
              controller: _title,
              decoration: const InputDecoration(
                  labelText: 'Title', border: OutlineInputBorder())),
          const SizedBox(height: 10),
          TextField(
              controller: _desc,
              maxLines: 3,
              decoration: const InputDecoration(
                  labelText: 'Details (optional)',
                  border: OutlineInputBorder())),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
              initialValue: _priority,
              decoration: const InputDecoration(
                  labelText: 'Priority', border: OutlineInputBorder()),
              items: const ['low', 'medium', 'high']
                  .map((p) => DropdownMenuItem(value: p, child: Text(p)))
                  .toList(),
              onChanged: (v) => setState(() => _priority = v ?? 'medium')),
        ]),
      ),
      actions: [
        TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel')),
        FilledButton(
            onPressed: _busy
                ? null
                : () async {
                    setState(() => _busy = true);
                    try {
                      await ApiService.raiseComplaint(
                          title: _title.text.trim(),
                          description: _desc.text.trim(),
                          priority: _priority);
                      if (context.mounted) Navigator.pop(context, true);
                    } on ApiException catch (e) {
                      setState(() => _busy = false);
                      if (!context.mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(e.message)));
                    }
                  },
            child: Text(_busy ? 'Saving…' : 'Save')),
      ],
    );
  }
}
