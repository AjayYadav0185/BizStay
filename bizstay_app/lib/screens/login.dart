import 'package:flutter/material.dart';

import '../api_service.dart';
import '../widgets/biz.dart';
import 'manager_shell.dart';
import 'tenant_shell.dart';

/// One login for both roles: managers use email, tenants use phone number.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.onSignedIn});

  final void Function(Session session) onSignedIn;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _login = TextEditingController();
  final _password = TextEditingController();
  final _url = TextEditingController();
  bool _busy = false;
  bool _obscure = true;
  String? _error;

  Future<void> submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final res = await ApiService.login(_login.text, _password.text);
      if (!mounted) return;
      final s = Session(
          role: res.role, name: res.name.isEmpty ? _login.text : res.name);
      widget.onSignedIn(s);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(
          () => _error = 'Could not reach the server. Check the API URL.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> openSettings() async {
    _url.text = await ApiService.baseUrl();
    if (!mounted) return;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 20,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 20),
        child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Server (API URL)',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 6),
              const Text(
                  'Emulator: http://10.0.2.2:8000/api. Real phone: http://<mac-ip>:8000/api',
                  style: TextStyle(fontSize: 12, color: Colors.black54)),
              const SizedBox(height: 10),
              TextField(
                  controller: _url,
                  decoration: const InputDecoration(
                      border: OutlineInputBorder(), hintText: 'https://.../api')),
              const SizedBox(height: 12),
              FilledButton(
                  onPressed: () async {
                    await ApiService.saveBaseUrl(_url.text);
                    if (ctx.mounted) Navigator.pop(ctx);
                  },
                  child: const Text('Save')),
            ]),
      ),
    );
  }

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    _url.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Biz.bg,
      appBar: AppBar(title: const Text('BizStay'), actions: [
        IconButton(
            onPressed: openSettings,
            icon: const Icon(Icons.settings_outlined),
            tooltip: 'Server URL'),
      ]),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(22),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Icon(Icons.apartment_rounded,
                          size: 46, color: Biz.primary),
                      const SizedBox(height: 8),
                      const Text('Welcome to BizStay',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                              fontSize: 20, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 4),
                      const Text(
                          'Manager: email. Resident: phone number.',
                          textAlign: TextAlign.center,
                          style:
                              TextStyle(color: Colors.black54, fontSize: 13)),
                      const SizedBox(height: 18),
                      TextField(
                          controller: _login,
                          keyboardType: TextInputType.emailAddress,
                          decoration: const InputDecoration(
                              labelText: 'Email or phone number',
                              border: OutlineInputBorder(),
                              prefixIcon: Icon(Icons.person_outline))),
                      const SizedBox(height: 12),
                      TextField(
                          controller: _password,
                          obscureText: _obscure,
                          onSubmitted: (_) => submit(),
                          decoration: InputDecoration(
                              labelText: 'Password',
                              border: const OutlineInputBorder(),
                              prefixIcon: const Icon(Icons.lock_outline),
                              suffixIcon: IconButton(
                                  onPressed: () =>
                                      setState(() => _obscure = !_obscure),
                                  icon: Icon(_obscure
                                      ? Icons.visibility_off
                                      : Icons.visibility)))),
                      if (_error != null) ...[
                        const SizedBox(height: 10),
                        Text(_error!,
                            style: const TextStyle(color: Colors.red)),
                      ],
                      const SizedBox(height: 16),
                      FilledButton(
                          onPressed: _busy ? null : submit,
                          child: Padding(
                              padding:
                                  const EdgeInsets.symmetric(vertical: 4),
                              child: _busy
                                  ? const SizedBox(
                                      height: 20,
                                      width: 20,
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          color: Colors.white))
                                  : const Text('Sign in'))),
                    ]),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class RoleRouter extends StatelessWidget {
  const RoleRouter(
      {super.key, required this.session, required this.onSignOut});

  final Session session;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    if (session.isTenant) {
      return TenantShell(session: session, onSignOut: onSignOut);
    }
    return ManagerShell(session: session, onSignOut: onSignOut);
  }
}
