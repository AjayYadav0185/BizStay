import 'package:flutter/material.dart';

import '../api_service.dart';
import '../theme/bizstay_colors.dart';
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
                  'Emulator: https://bizstay.onrender.com/api. Real phone: http://<mac-ip>:8000/api',
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
      backgroundColor: BizStayColors.slate100,
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: ConstrainedBox(
            constraints:
                const BoxConstraints(maxWidth: 420),
            child: Column(
                crossAxisAlignment:
                    CrossAxisAlignment.stretch,
                children: [
                  Container(
                    padding:
                        const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                        gradient:
                            const LinearGradient(
                                begin: Alignment
                                    .topLeft,
                                end: Alignment
                                    .bottomRight,
                                colors: [
                                  BizStayColors
                                      .navy900,
                                  Color(0xFF27365A)
                                ]),
                        borderRadius:
                            BorderRadius.circular(
                                24)),
                    child: Column(children: [
                      Container(
                        width: 84,
                        height: 84,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius:
                              BorderRadius.circular(
                                  22),
                        ),
                        padding:
                            const EdgeInsets.all(
                                8),
                        child: ClipRRect(
                          borderRadius:
                              BorderRadius.circular(
                                  14),
                          child: Image.asset(
                            'assets/icons/bizstay_icon_master.png',
                            fit: BoxFit.cover,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      const Text('BizStay',
                          textAlign:
                              TextAlign.center,
                          style: TextStyle(
                              fontSize: 26,
                              fontWeight:
                                  FontWeight.w900,
                              color: Colors.white,
                              letterSpacing: -0.5)),
                      const Text(
                          'PG + HOTEL • GURGAON',
                          textAlign:
                              TextAlign.center,
                          style: TextStyle(
                              fontSize: 11,
                              fontWeight:
                                  FontWeight.w800,
                              letterSpacing: 1.6,
                              color:
                                  BizStayColors
                                      .gold500)),
                    ]),
                  ),
                  const SizedBox(height: 14),
                  Card(
                    child: Padding(
                      padding:
                          const EdgeInsets.all(20),
                      child: Column(
                          crossAxisAlignment:
                              CrossAxisAlignment
                                  .stretch,
                          children: [
                            const Text(
                                'Welcome back',
                                style: TextStyle(
                                    fontSize: 19,
                                    fontWeight:
                                        FontWeight
                                            .w800)),
                            const SizedBox(
                                height: 4),
                            const Text(
                                'Manager: email. Resident: phone number.',
                                style: TextStyle(
                                    color: BizStayColors
                                        .slate600,
                                    fontSize: 13)),
                            const SizedBox(
                                height: 16),
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
                        Container(
                          padding:
                              const EdgeInsets.all(
                                  10),
                          decoration: BoxDecoration(
                              color: BizStayColors
                                  .errorBg,
                              borderRadius:
                                  BorderRadius
                                      .circular(12)),
                          child: Text(_error!,
                              style: const TextStyle(
                                  color: BizStayColors
                                      .errorDark,
                                  fontWeight:
                                      FontWeight
                                          .w700)),
                        ),
                      ],
                      const SizedBox(height: 16),
                      FilledButton(
                          onPressed:
                              _busy ? null : submit,
                          child: Padding(
                              padding:
                                  const EdgeInsets
                                      .symmetric(
                                          vertical:
                                              4),
                              child: _busy
                                  ? const SizedBox(
                                      height: 20,
                                      width: 20,
                                      child: CircularProgressIndicator(
                                          strokeWidth:
                                              2,
                                          color: Colors
                                              .white))
                                  : const Text(
                                      'Sign in'))),
                      TextButton.icon(
                          onPressed: openSettings,
                          icon: const Icon(
                              Icons.settings_outlined,
                              size: 16),
                          label: const Text(
                              'Change server URL')),
                    ]),
              ),
            ),
            ],
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
