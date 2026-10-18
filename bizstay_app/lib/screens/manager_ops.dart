import 'package:flutter/material.dart';

import '../api_service.dart';
import '../theme/bizstay_colors.dart';
import '../widgets/biz.dart';
import '../widgets/bizstay_ui.dart';

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
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          if (_tabs.index == 1) {
            if (context.mounted) {
              showAdminOnlySheet(context, 'Leads');
            }
            return;
          }
          final ok = await showDialog<bool>(
              context: context,
              builder: (ctx) => const ComplaintDialog());
          if (ok == true && context.mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Complaint logged.')));
            setState(() {});
          }
        },
        tooltip: 'Add',
        icon: const Icon(Icons.add),
        label: Text(
            _tabs.index == 1 ? 'Add lead' : 'Log complaint'),
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
  final _search = TextEditingController();
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.complaints();
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
    return rows.where((c) {
      final hay =
          '${c['title']} ${c['guest']} ${c['room']} ${c['status']}'
              .toLowerCase();
      return hay.contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Padding(
        padding:
            const EdgeInsets.fromLTRB(12, 12, 12, 4),
        child: SearchField(
            controller: _search,
            hint: 'Search complaints…',
            onSearch: () => setState(() {})),
      ),
      Expanded(
        child: FutureBuilder<Map<String, dynamic>>(
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
        final rows =
            _visible(ApiService.pageItems(snap.data));
        if (rows.isEmpty) {
          return const EmptyState(
              icon: Icons.handyman_outlined,
              message: 'No complaints match.');
        }
        return RefreshIndicator(
          onRefresh: () async {
            final v = await ApiService.complaints();
            setState(() => _future = Future.value(v));
          },
          child: ListView.builder(
            padding:
                const EdgeInsets.fromLTRB(12, 4, 12, 90),
            itemCount: rows.length,
            itemBuilder: (context, i) {
              final c = rows[i];
              final breached = c['sla_breached'] == true;
              return Card(
                margin:
                    const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: Icon(Icons.handyman,
                      color: BizStayColors.statusColor(
                          '${c['status']}')),
                  title:
                      Text('${c['title'] ?? ''}'),
                  subtitle: Text(
                      '${c['guest'] ?? '—'} • Room ${c['room'] ?? '—'} • ${c['created'] ?? ''}'),
                  trailing: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment:
                          CrossAxisAlignment.end,
                      children: [
                        StatusChip(
                            '${c['status'] ?? ''}'),
                        if (breached)
                          const Text('SLA breached',
                              style: TextStyle(
                                  color: BizStayColors
                                      .errorDark,
                                  fontSize: 11,
                                  fontWeight:
                                      FontWeight.w700)),
                      ]),
                ),
              );
            },
          ),
        );
      },
        ),
      ),
    ]);
  }
}

class _LeadList extends StatefulWidget {
  const _LeadList();

  @override
  State<_LeadList> createState() => _LeadListState();
}

class _LeadListState extends State<_LeadList> {
  final _search = TextEditingController();
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.inquiries();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Padding(
        padding:
            const EdgeInsets.fromLTRB(12, 12, 12, 4),
        child: SearchField(
            controller: _search,
            hint: 'Search leads…',
            onSearch: () => setState(() {})),
      ),
      Expanded(
        child: FutureBuilder<Map<String, dynamic>>(
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
        final q = _search.text.trim().toLowerCase();
        final all = ApiService.pageItems(snap.data);
        final rows = q.isEmpty
            ? all
            : all.where((l) {
                final hay =
                    '${l['name']} ${l['phone']} ${l['source']} ${l['status']}'
                        .toLowerCase();
                return hay.contains(q);
              }).toList();
        if (rows.isEmpty) {
          return const EmptyState(
              icon: Icons.person_add_alt_outlined,
              message: 'No leads match.');
        }
        return ListView.builder(
          padding:
              const EdgeInsets.fromLTRB(12, 4, 12, 90),
          itemCount: rows.length,
          itemBuilder: (context, i) {
            final l = rows[i];
            final overdue = l['overdue'] == true;
            return Card(
              margin:
                  const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: CircleAvatar(
                    backgroundColor:
                        BizStayColors.navy900,
                    child: Text(
                        ('${l['name'] ?? '?'}')
                            .substring(0, 1)
                            .toUpperCase(),
                        style: const TextStyle(
                            color: Colors.white,
                            fontWeight:
                                FontWeight.w800))),
                title: Text(
                    '${l['name'] ?? ''} • ${l['phone'] ?? ''}'),
                subtitle: Text(
                    '${l['source'] ?? ''} • Follow-up ${l['follow_up'] ?? '—'}${overdue ? ' • OVERDUE' : ''}'),
                trailing:
                    StatusChip('${l['status'] ?? ''}'),
              ),
            );
          },
        );
      },
        ),
      ),
    ]);
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
