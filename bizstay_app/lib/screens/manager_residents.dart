import 'package:flutter/material.dart';

import '../api_service.dart';
import '../widgets/biz.dart';

class ManagerResidents extends StatefulWidget {
  const ManagerResidents({super.key});

  @override
  State<ManagerResidents> createState() => _ManagerResidentsState();
}

class _ManagerResidentsState extends State<ManagerResidents> {
  final _search = TextEditingController();
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.guests();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _reload() {
    setState(() => _future = ApiService.guests(search: _search.text.trim()));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(title: const Text('Residents')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: TextField(
            controller: _search,
            onSubmitted: (_) => _reload(),
            decoration: InputDecoration(
                hintText: 'Search name or phone',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                    onPressed: _reload, icon: const Icon(Icons.arrow_forward)),
                border: const OutlineInputBorder(),
                filled: true,
                fillColor: Colors.white),
          ),
        ),
        Expanded(
          child: FutureBuilder<Map<String, dynamic>>(
            future: _future,
            builder: (context, snap) {
              if (snap.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snap.hasError) {
                return Biz.errorBox('Could not load residents.',
                    onRetry: _reload);
              }
              final rows = ApiService.pageItems(snap.data);
              if (rows.isEmpty) {
                return const Center(child: Text('No residents found.'));
              }
              return RefreshIndicator(
                onRefresh: () async {
                  final v = await ApiService.guests(
                      search: _search.text.trim());
                  setState(() => _future = Future.value(v));
                },
                child: ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: rows.length,
                  itemBuilder: (context, i) {
                    final g = rows[i];
                    final due = ApiService.numOf(g['outstanding']);
                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: CircleAvatar(
                            child: Text(
                                (g['full_name'] ?? '?').toString().substring(0, 1).toUpperCase())),
                        title: Text('${g['full_name'] ?? ''}'),
                        subtitle: Text(
                            '${g['phone'] ?? ''} • Bed ${g['bed'] ?? '—'} • ${g['aadhaar'] ?? ''}'),
                        trailing: Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Biz.chip('${g['kyc_status'] ?? ''}'),
                              const SizedBox(height: 4),
                              Text(Biz.money(due),
                                  style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      color: due > 0
                                          ? Colors.red
                                          : Colors.green)),
                            ]),
                      ),
                    );
                  },
                ),
              );
            },
          ),
        ),
      ]),
    );
  }
}
