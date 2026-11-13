import 'package:flutter/material.dart';

import '../api_service.dart';
import '../links.dart';
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
  late final TabController _tabs = TabController(length: 3, vsync: this)
    ..addListener(() {
      if (!_tabs.indexIsChanging && mounted) setState(() {});
    });

  /// Bumped after an add so the affected list reloads.
  int _refresh = 0;

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  String get _fabLabel => switch (_tabs.index) {
        1 => 'Add lead',
        2 => 'Log visitor',
        _ => 'Log complaint',
      };

  Future<void> _add() async {
    final Widget dialog = switch (_tabs.index) {
      1 => const LeadDialog(),
      2 => const VisitorDialog(),
      _ => const ComplaintDialog(),
    };
    final done = switch (_tabs.index) {
      1 => 'Lead saved.',
      2 => 'Visitor logged in.',
      _ => 'Complaint logged.',
    };
    final ok = await showDialog<bool>(context: context, builder: (_) => dialog);
    if (ok == true && mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(done)));
      setState(() => _refresh++);
    }
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
            Tab(text: 'Visitors'),
          ])),
      body: TabBarView(controller: _tabs, children: [
        _ComplaintList(key: ValueKey('c$_refresh')),
        _LeadList(key: ValueKey('l$_refresh')),
        _VisitorList(key: ValueKey('v$_refresh')),
      ]),
      floatingActionButton: AddFab(label: _fabLabel, onPressed: _add),
    );
  }
}

class _ComplaintList extends StatefulWidget {
  const _ComplaintList({super.key});

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
  const _LeadList({super.key});

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
                onTap: '${l['phone'] ?? ''}'.isEmpty
                    ? null
                    : () => Links.call(context, '${l['phone']}'),
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

class _VisitorList extends StatefulWidget {
  const _VisitorList({super.key});

  @override
  State<_VisitorList> createState() => _VisitorListState();
}

class _VisitorListState extends State<_VisitorList> {
  bool _insideOnly = true;
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() =>
      ApiService.visitors(insideOnly: _insideOnly);

  void _reload() => setState(() => _future = _load());

  Future<void> _checkout(Map<String, dynamic> v) async {
    try {
      await ApiService.checkoutVisitor((v['id'] as num).toInt());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${v['visitor'] ?? 'Visitor'} checked out.')));
      _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  static String _time(dynamic iso) {
    final t = DateTime.tryParse('${iso ?? ''}')?.toLocal();
    if (t == null) return '—';
    final h = t.hour % 12 == 0 ? 12 : t.hour % 12;
    final m = t.minute.toString().padLeft(2, '0');
    return '${t.day}/${t.month} $h:$m ${t.hour < 12 ? 'AM' : 'PM'}';
  }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 2),
        child: Row(children: [
          ChoiceChip(
              label: const Text('Inside now'),
              selected: _insideOnly,
              onSelected: (_) {
                _insideOnly = true;
                _reload();
              }),
          const SizedBox(width: 8),
          ChoiceChip(
              label: const Text('All'),
              selected: !_insideOnly,
              onSelected: (_) {
                _insideOnly = false;
                _reload();
              }),
        ]),
      ),
      Expanded(
        child: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return Biz.errorBox('Could not load visitors.',
                  onRetry: _reload);
            }
            final rows = ApiService.pageItems(snap.data);
            if (rows.isEmpty) {
              return EmptyState(
                  icon: Icons.badge_outlined,
                  message: _insideOnly
                      ? 'Nobody inside right now.'
                      : 'No visitors logged yet.');
            }
            return RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView.builder(
                padding: const EdgeInsets.fromLTRB(12, 4, 12, 90),
                itemCount: rows.length,
                itemBuilder: (context, i) {
                  final v = rows[i];
                  final inside = v['out'] == null;
                  final meeting = v['meeting'] == null
                      ? 'Office / service'
                      : 'Meeting ${v['meeting']}${v['bed'] != null ? ' (${v['bed']})' : ''}';
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: CircleAvatar(
                          backgroundColor: inside
                              ? BizStayColors.warningBg
                              : BizStayColors.slate100,
                          child: Icon(Icons.badge_outlined,
                              color: inside
                                  ? BizStayColors.warning
                                  : BizStayColors.slate500)),
                      title: Text(
                          '${v['visitor'] ?? ''}${v['overnight'] == true ? ' • Overnight' : ''}'),
                      subtitle: Text(
                          '$meeting\n${v['relation'] ?? ''} • In ${_time(v['in'])}${inside ? '' : ' • Out ${_time(v['out'])}'}'),
                      isThreeLine: true,
                      trailing: inside
                          ? FilledButton.tonal(
                              onPressed: () => _checkout(v),
                              child: const Text('Out'))
                          : null,
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

class VisitorDialog extends StatefulWidget {
  const VisitorDialog({super.key});

  @override
  State<VisitorDialog> createState() => _VisitorDialogState();
}

class _VisitorDialogState extends State<VisitorDialog> {
  static const relations = [
    'Parent', 'Sibling', 'Relative', 'Friend', 'Colleague',
    'Delivery', 'Service / repair', 'Prospective tenant', 'Other',
  ];

  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _purpose = TextEditingController();
  String _relation = 'Parent';
  int? _guestId;
  bool _overnight = false;
  bool _busy = false;
  late final Future<Map<String, dynamic>> _residents = ApiService.guests();

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _purpose.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Enter the visitor\'s name.')));
      return;
    }
    setState(() => _busy = true);
    try {
      await ApiService.addVisitor(
          name: _name.text.trim(),
          phone: _phone.text.trim(),
          guestId: _guestId,
          relation: _relation,
          purpose: _purpose.text.trim(),
          overnight: _overnight);
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Log visitor'),
      content: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(
              controller: _name,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                  labelText: 'Visitor name', border: OutlineInputBorder())),
          const SizedBox(height: 10),
          TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                  labelText: 'Mobile (optional)',
                  border: OutlineInputBorder())),
          const SizedBox(height: 10),
          FutureBuilder<Map<String, dynamic>>(
            future: _residents,
            builder: (context, snap) {
              final staying = ApiService.pageItems(snap.data)
                  .where((g) => g['bed'] != null)
                  .toList();
              return DropdownButtonFormField<int?>(
                initialValue: _guestId,
                isExpanded: true,
                decoration: const InputDecoration(
                    labelText: 'Meeting resident',
                    border: OutlineInputBorder()),
                items: [
                  const DropdownMenuItem<int?>(
                      value: null, child: Text('Office / service / none')),
                  ...staying.map((g) => DropdownMenuItem<int?>(
                      value: (g['id'] as num).toInt(),
                      child: Text('${g['full_name']} • ${g['bed']}',
                          overflow: TextOverflow.ellipsis))),
                ],
                onChanged: (v) => setState(() => _guestId = v),
              );
            },
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
              initialValue: _relation,
              decoration: const InputDecoration(
                  labelText: 'Relation', border: OutlineInputBorder()),
              items: relations
                  .map((r) => DropdownMenuItem(value: r, child: Text(r)))
                  .toList(),
              onChanged: (v) => setState(() => _relation = v ?? 'Other')),
          const SizedBox(height: 10),
          TextField(
              controller: _purpose,
              decoration: const InputDecoration(
                  labelText: 'Purpose (optional)',
                  border: OutlineInputBorder())),
          SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Staying overnight'),
              value: _overnight,
              onChanged: (v) => setState(() => _overnight = v)),
        ]),
      ),
      actions: [
        TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel')),
        FilledButton(
            onPressed: _busy ? null : _save,
            child: Text(_busy ? 'Saving…' : 'Log in')),
      ],
    );
  }
}

class LeadDialog extends StatefulWidget {
  const LeadDialog({super.key});

  @override
  State<LeadDialog> createState() => _LeadDialogState();
}

class _LeadDialogState extends State<LeadDialog> {
  static const sources = {
    'walk_in': 'Walk-in',
    'phone': 'Phone',
    'whatsapp': 'WhatsApp',
    'google': 'Google / Maps',
    'instagram': 'Instagram / Facebook',
    'referral': 'Referral',
    'nobroker': 'NoBroker',
    '99acres': '99acres',
    'magicbricks': 'MagicBricks',
    'housing': 'Housing.com',
    'justdial': 'Justdial',
    'corporate': 'Corporate tie-up',
    'college': 'College / Coaching',
    'other': 'Other',
  };

  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _budget = TextEditingController();
  final _note = TextEditingController();
  String _source = 'walk_in';
  bool _busy = false;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _budget.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final phone = _phone.text.replaceAll(RegExp(r'\D'), '');
    if (_name.text.trim().isEmpty || phone.length < 10) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Enter a name and a 10-digit mobile number.')));
      return;
    }
    setState(() => _busy = true);
    try {
      await ApiService.addLead(
          name: _name.text.trim(),
          phone: phone.substring(phone.length - 10),
          source: _source,
          budget: double.tryParse(_budget.text.trim()),
          message: _note.text.trim());
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Add lead'),
      content: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(
              controller: _name,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                  labelText: 'Name', border: OutlineInputBorder())),
          const SizedBox(height: 10),
          TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                  labelText: 'Mobile', border: OutlineInputBorder())),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
              initialValue: _source,
              isExpanded: true,
              decoration: const InputDecoration(
                  labelText: 'Source', border: OutlineInputBorder()),
              items: sources.entries
                  .map((e) =>
                      DropdownMenuItem(value: e.key, child: Text(e.value)))
                  .toList(),
              onChanged: (v) => setState(() => _source = v ?? 'walk_in')),
          const SizedBox(height: 10),
          TextField(
              controller: _budget,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                  labelText: 'Budget per month (optional)',
                  prefixText: '₹ ',
                  border: OutlineInputBorder())),
          const SizedBox(height: 10),
          TextField(
              controller: _note,
              maxLines: 2,
              decoration: const InputDecoration(
                  labelText: 'Notes (office, move-in date…)',
                  border: OutlineInputBorder())),
        ]),
      ),
      actions: [
        TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel')),
        FilledButton(
            onPressed: _busy ? null : _save,
            child: Text(_busy ? 'Saving…' : 'Save')),
      ],
    );
  }
}
