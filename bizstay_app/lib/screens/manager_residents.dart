import 'package:flutter/material.dart';

import '../api_service.dart';
import '../theme/bizstay_colors.dart';
import '../widgets/biz.dart';
import '../widgets/bizstay_ui.dart';

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
      appBar: AppBar(
        title: const Text('Residents'),
        actions: [
          AddAppBarButton(
              tooltip: 'Add resident',
              onPressed: () =>
                  showAdminOnlySheet(
                      context, 'Residents')),
        ],
      ),
      floatingActionButton: AddFab(
        label: 'Add resident',
        onPressed: () => showAdminOnlySheet(
            context, 'Residents'),
      ),
      body: Column(children: [
        Padding(
          padding:
              const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: SearchField(
            controller: _search,
            hint: 'Search name or phone',
            onSearch: _reload,
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
                return const EmptyState(
                    icon: Icons.people_outline,
                    message:
                        'No residents found. Try another search.');
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
                      margin:
                          const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: CircleAvatar(
                            backgroundColor:
                                BizStayColors.navy900,
                            child: Text(
                                (g['full_name'] ?? '?')
                                    .toString()
                                    .substring(0, 1)
                                    .toUpperCase(),
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight:
                                        FontWeight.w800))),
                        title: Text(
                            '${g['full_name'] ?? ''}'),
                        subtitle: Text(
                            '${g['phone'] ?? ''} • Bed ${g['bed'] ?? '—'} • Room ${g['room'] ?? '—'}'),
                        trailing: Column(
                            mainAxisSize:
                                MainAxisSize.min,
                            crossAxisAlignment:
                                CrossAxisAlignment.end,
                            children: [
                              StatusChip(
                                  '${g['kyc_status'] ?? ''}'),
                              const SizedBox(height: 4),
                              MoneyText(
                                  Biz.money(due),
                                  style: TextStyle(
                                      fontSize: 14,
                                      color: due > 0
                                          ? BizStayColors
                                              .errorDark
                                          : BizStayColors
                                              .successDark)),
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
