import 'package:flutter/material.dart';

import '../api_service.dart';
import 'manager_billing.dart';
import 'manager_ops.dart';
import 'manager_residents.dart';
import 'manager_rooms.dart';
import 'manager_today.dart';

class ManagerShell extends StatefulWidget {
  const ManagerShell(
      {super.key, required this.session, required this.onSignOut});

  final Session session;
  final VoidCallback onSignOut;

  @override
  State<ManagerShell> createState() => _ManagerShellState();
}

class _ManagerShellState extends State<ManagerShell> {
  int _tab = 0;

  Future<void> _logout() async {
    await ApiService.logout();
    widget.onSignOut();
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      ManagerToday(onLogout: _logout, name: widget.session.name),
      const ManagerRooms(),
      const ManagerResidents(),
      const ManagerBilling(),
      const ManagerOps(),
    ];
    return Scaffold(
      body: pages[_tab],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.today_outlined),
              selectedIcon: Icon(Icons.today),
              label: 'Today'),
          NavigationDestination(
              icon: Icon(Icons.bed_outlined),
              selectedIcon: Icon(Icons.bed),
              label: 'Rooms'),
          NavigationDestination(
              icon: Icon(Icons.people_outline),
              selectedIcon: Icon(Icons.people),
              label: 'Residents'),
          NavigationDestination(
              icon: Icon(Icons.receipt_long_outlined),
              selectedIcon: Icon(Icons.receipt_long),
              label: 'Billing'),
          NavigationDestination(
              icon: Icon(Icons.handyman_outlined),
              selectedIcon: Icon(Icons.handyman),
              label: 'Ops'),
        ],
      ),
    );
  }
}
