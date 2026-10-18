import 'package:flutter/material.dart';

import '../api_service.dart';
import '../theme/bizstay_colors.dart';
import '../widgets/biz.dart';
import '../widgets/bizstay_ui.dart';

class ManagerRooms extends StatefulWidget {
  const ManagerRooms({super.key});

  @override
  State<ManagerRooms> createState() => _ManagerRoomsState();
}

class _ManagerRoomsState extends State<ManagerRooms> {
  final _search = TextEditingController();
  late Future<Map<String, dynamic>> _future;
  String _status = 'all';

  @override
  void initState() {
    super.initState();
    _future = ApiService.rooms();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> _visible(List<Map<String, dynamic>> rooms) {
    final q = _search.text.trim().toLowerCase();
    return rooms.where((r) {
      if (_status == 'vacant' &&
          (ApiService.numOf(r['occupancy_percent']) >= 100)) {
        return false;
      }
      if (_status == 'occupied' &&
          (ApiService.numOf(r['occupancy_percent']) <= 0)) {
        return false;
      }
      if (q.isEmpty) return true;
      final beds = (r['beds'] as List?) ?? [];
      final hay =
          '${r['room_number']} ${r['floor_no']} ${r['sharing_type']} '
          '${beds.map((b) => '${(b as Map)['bed_code']} ${(b)['guest'] ?? ''}').join(' ')}'
              .toLowerCase();
      return hay.contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(
        title: const Text('Rooms & beds'),
        actions: [
          IconButton(
              tooltip: 'Add room',
              onPressed: () =>
                  showAdminOnlySheet(context, 'Rooms'),
              icon: const Icon(Icons.add)),
        ],
      ),
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
          final rooms = _visible(ApiService.pageItems(snap.data));
          if (rooms.isEmpty) {
            return Column(children: [
              _RoomsSearchBar(search: _search, onGo: () => setState(() {})),
              const Expanded(
                  child: EmptyState(
                      icon: Icons.bed_outlined,
                      message: 'No rooms match. Try another search.')),
            ]);
          }
          return RefreshIndicator(
            onRefresh: () async {
              final v = await ApiService.rooms();
              setState(() => _future = Future.value(v));
            },
            child: CustomScrollView(slivers: [
              SliverToBoxAdapter(
                  child: _RoomsSearchBar(
                      search: _search,
                      status: _status,
                      total: rooms.length,
                      onGo: () => setState(() {}),
                      onStatus: (s) =>
                          setState(() => _status = s))),
              SliverPadding(
                padding:
                    const EdgeInsets.fromLTRB(12, 0, 12, 24),
                sliver: SliverList.builder(
                  itemCount: rooms.length,
                  itemBuilder: (context, i) {
                    final r = rooms[i];
                    final beds = (r['beds'] as List?) ?? [];
                    final occ = ApiService.numOf(
                        r['occupancy_percent']);
                    final free =
                        beds.where((b) => '${(b as Map)['status']}' ==
                            'available').length;
                    return _RoomCard(
                        room: r,
                        beds: beds,
                        occ: occ,
                        free: free);
                  },
                ),
              ),
            ]),
          );
        },
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () =>
            showAdminOnlySheet(context, 'Rooms'),
        icon: const Icon(Icons.add),
        label: const Text('Add room'),
      ),
    );
  }
}

/// Sticky OYO-style search + vacancy filter + count.
class _RoomsSearchBar extends StatelessWidget {
  const _RoomsSearchBar(
      {required this.search,
      this.status = 'all',
      this.total,
      required this.onGo,
      this.onStatus});

  final TextEditingController search;
  final String status;
  final int? total;
  final VoidCallback onGo;
  final void Function(String s)? onStatus;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding:
          const EdgeInsets.fromLTRB(12, 12, 12, 8),
      child: Column(children: [
        SearchField(
            controller: search,
            hint: 'Search room, bed, guest…',
            onSearch: onGo),
        const SizedBox(height: 8),
        Row(children: [
          for (final s in [
            'all',
            'vacant',
            'occupied'
          ])
            Padding(
              padding:
                  const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                  label: Text(s),
                  selected: status == s,
                  onSelected: (_) =>
                      onStatus?.call(s)),
            ),
          const Spacer(),
          if (total != null)
            CountPill('$total found'),
        ]),
      ]),
    );
  }
}

// MARKER_ROOMCARD
/// OYO-style room card: navy header, rate row, bed list.
class _RoomCard extends StatelessWidget {
  const _RoomCard(
      {required this.room,
      required this.beds,
      required this.occ,
      required this.free});
  final Map<String, dynamic> room;
  final List beds;
  final double occ;
  final int free;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      clipBehavior: Clip.antiAlias,
      child: Column(children: [
        _RoomHead(room: room, occ: occ, free: free),
        _RoomRates(room: room),
        const Divider(height: 1),
        ...beds.map<Widget>((b) {
          final bed =
              (b as Map).cast<String, dynamic>();
          final st = '${bed['status']}';
          return ListTile(
            dense: true,
            leading: Icon(
                st == 'occupied'
                    ? Icons.bed
                    : Icons.bed_outlined,
                color: BizStayColors
                    .statusColor(st)),
            title: Text('${bed['bed_code']}',
                style: const TextStyle(
                    fontWeight:
                        FontWeight.w700)),
            subtitle: bed['guest'] != null
                ? Text('${bed['guest']}',
                    maxLines: 1,
                    overflow:
                        TextOverflow.ellipsis)
                : const Text('Vacant',
                    style: TextStyle(
                        color: BizStayColors
                            .successDark)),
            trailing: StatusChip(st),
          );
        }),
      ]),
    );
  }
}

// MARKER_ROOMHEAD
class _RoomHead extends StatelessWidget {
  const _RoomHead(
      {required this.room,
      required this.occ,
      required this.free});
  final Map<String, dynamic> room;
  final double occ;
  final int free;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: const BoxDecoration(
          gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
            BizStayColors.navy900,
            Color(0xFF334155)
          ])),
      child: Column(children: [
        Row(children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
                color: Colors.white
                    .withValues(alpha: 0.14),
                borderRadius:
                    BorderRadius.circular(14)),
            alignment: Alignment.center,
            child: Text(
                '${room['room_number'] ?? ''}',
                style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 17)),
          ),
          const SizedBox(width: 12),
          Expanded(
              child: Column(
                  crossAxisAlignment:
                      CrossAxisAlignment.start,
                  children: [
                Text(
                    'Room ${room['room_number'] ?? ''} • Floor ${room['floor_no'] ?? 0}',
                    style: const TextStyle(
                        color: Colors.white,
                        fontWeight:
                            FontWeight.w800,
                        fontSize: 15)),
                Text(
                    '${room['sharing_type'] ?? ''} sharing • ${occ.toStringAsFixed(0)}% full',
                    style: const TextStyle(
                        color: Colors.white70,
                        fontSize: 12)),
              ])),
          Container(
            padding:
                const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 5),
            decoration: BoxDecoration(
                color: free > 0
                    ? BizStayColors.successBg
                    : BizStayColors.errorBg,
                borderRadius:
                    BorderRadius.circular(99)),
            child: Text(
                free > 0 ? '$free FREE' : 'FULL',
                style: TextStyle(
                    fontSize: 11,
                    fontWeight:
                        FontWeight.w900,
                    color: free > 0
                        ? BizStayColors
                            .successDark
                        : BizStayColors
                            .errorDark)),
          ),
        ]),
        const SizedBox(height: 8),
        Row(children: [
          if (room['has_ac'] == true)
            const StatusChip('AC',
                color: BizStayColors.info),
          if (room['has_ac'] == true)
            const SizedBox(width: 6),
          StatusChip(
              '${room['status'] ?? ''}'),
        ]),
      ]),
    );
  }
}

class _RoomRates extends StatelessWidget {
  const _RoomRates({required this.room});
  final Map<String, dynamic> room;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Row(children: [
        Expanded(
            child: Text(
                'PG ${Biz.money(room['base_rent_per_bed'] ?? 0)}/bed',
                style: const TextStyle(
                    fontWeight:
                        FontWeight.w800,
                    fontSize: 13))),
        Expanded(
            child: Text(
                'Hotel ${Biz.money(room['nightly_rate'] ?? 0)}/night',
                textAlign: TextAlign.end,
                style: const TextStyle(
                    fontWeight:
                        FontWeight.w800,
                    fontSize: 13))),
      ]),
    );
  }
}
