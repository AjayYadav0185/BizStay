import 'package:flutter/material.dart';

import '../api_service.dart';
import '../widgets/biz.dart';

class ManagerRooms extends StatefulWidget {
  const ManagerRooms({super.key});

  @override
  State<ManagerRooms> createState() => _ManagerRoomsState();
}

class _ManagerRoomsState extends State<ManagerRooms> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.rooms();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(title: const Text('Rooms & beds')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Biz.errorBox('Could not load rooms.',
                onRetry: () => setState(() => _future = ApiService.rooms()));
          }
          final rooms = ApiService.pageItems(snap.data);
          if (rooms.isEmpty) {
            return const Center(child: Text('No rooms yet.'));
          }
          return RefreshIndicator(
            onRefresh: () async {
              final v = await ApiService.rooms();
              setState(() => _future = Future.value(v));
            },
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: rooms.length,
              itemBuilder: (context, i) {
                final r = rooms[i];
                final beds = (r['beds'] as List?) ?? [];
                return Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ExpansionTile(
                    leading: CircleAvatar(
                        backgroundColor: Biz.primary.withValues(alpha: 0.12),
                        child: Text('${r['room_number'] ?? ''}',
                            style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: Biz.primary))),
                    title: Text(
                        'Room ${r['room_number'] ?? ''} • Floor ${r['floor_no'] ?? 0}'),
                    subtitle: Text(
                        '${r['sharing_type'] ?? ''} • PG ${Biz.money(r['base_rent_per_bed'] ?? 0)}/bed • Hotel ${Biz.money(r['nightly_rate'] ?? 0)}/night'),
                    trailing: Biz.chip('${r['status'] ?? ''}'),
                    children: beds.map<Widget>((b) {
                      final bed = (b as Map).cast<String, dynamic>();
                      return ListTile(
                        dense: true,
                        leading: Icon(
                            bed['status'] == 'occupied'
                                ? Icons.bed
                                : Icons.bed_outlined,
                            color: Biz.statusColor('${bed['status']}')),
                        title: Text('${bed['bed_code']}'),
                        subtitle: bed['guest'] != null
                            ? Text('${bed['guest']}')
                            : null,
                        trailing: Biz.chip('${bed['status']}'),
                      );
                    }).toList(),
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
