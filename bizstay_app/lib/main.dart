import 'package:flutter/material.dart';

import 'api_service.dart';
import 'screens/login.dart';
import 'widgets/biz.dart';

void main() {
  runApp(const BizStayApp());
}

class BizStayApp extends StatefulWidget {
  const BizStayApp({super.key});

  @override
  State<BizStayApp> createState() => BizStayAppState();
}

class BizStayAppState extends State<BizStayApp> {
  Session? _session;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    ApiService.session().then((s) {
      if (mounted) {
        setState(() {
          _session = s;
          _loading = false;
        });
      }
    });
  }

  void _signedIn(Session s) => setState(() => _session = s);

  void _signOut() => setState(() => _session = null);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'BizStay',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Biz.primary),
        useMaterial3: true,
      ),
      home: _loading
          ? const Scaffold(
              body: Center(child: CircularProgressIndicator()))
          : _session == null
              ? LoginScreen(onSignedIn: _signedIn)
              : RoleRouter(session: _session!, onSignOut: _signOut),
    );
  }
}

