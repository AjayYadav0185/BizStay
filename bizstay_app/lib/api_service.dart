import 'dart:convert';

import 'package:flutter/foundation.dart' show VoidCallback;
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Talks to the BizStay Laravel API (routes/api.php, Sanctum tokens).
class ApiService {
  ApiService._();

  static const _tokenKey = 'bizstay_token';
  static const _roleKey = 'bizstay_role';
  static const _nameKey = 'bizstay_name';
  static const _baseUrlKey = 'bizstay_base_url';

  /// Called when the server ends the session (token expired, login
  /// disabled by the owner) so the app can return to the sign-in screen.
  static VoidCallback? onSessionEnded;

  static const String defaultBaseUrl = String.fromEnvironment(
    'BIZSTAY_API_URL',
    defaultValue: 'https://bizstay.onrender.com/api',
  );

  static Future<String> baseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = (prefs.getString(_baseUrlKey) ?? '').trim();
    if (saved.isNotEmpty) {
      return saved.replaceAll(RegExp(r'/+$'), '');
    }
    return defaultBaseUrl.replaceAll(RegExp(r'/+$'), '');
  }

  static Future<void> saveBaseUrl(String url) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
        _baseUrlKey, url.trim().replaceAll(RegExp(r'/+$'), ''));
  }

  static Future<Map<String, String>> headers({bool json = false}) async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
    return {
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  static Future<http.Response> get(String path,
      [Map<String, String>? query]) async {
    final base = await baseUrl();
    final uri = Uri.parse('$base$path').replace(queryParameters: query);
    return http.get(uri, headers: await headers());
  }

  static Future<http.Response> post(
      String path, Map<String, dynamic> body) async {
    final base = await baseUrl();
    return http.post(Uri.parse('$base$path'),
        headers: await headers(json: true), body: jsonEncode(body));
  }

  static Future<LoginResult> login(String login, String password) async {
    final base = await baseUrl();
    final res = await http.post(Uri.parse('$base/login'),
        headers: const {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: jsonEncode({'login': login.trim(), 'password': password}));
    final body = decode(res);
    if (res.statusCode != 200) {
      throw ApiException(messageOf(body, 'Invalid credentials.'));
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, '${body['token'] ?? ''}');
    await prefs.setString(_roleKey, '${body['role'] ?? 'manager'}');
    final user = body['user'] as Map<String, dynamic>?;
    await prefs.setString(_nameKey, '${user?['name'] ?? login}');
    return LoginResult(
        role: '${body['role'] ?? 'manager'}',
        name: '${user?['name'] ?? ''}');
  }

  static Future<void> logout() async {
    try {
      await post('/logout', const {});
    } catch (_) {
      // Token may already be invalid; still clear local state.
    }
    await _clearSession();
  }

  static Future<Session?> session() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
    if (token == null || token.isEmpty) return null;
    return Session(
        role: prefs.getString(_roleKey) ?? 'manager',
        name: prefs.getString(_nameKey) ?? '');
  }

  static Future<Map<String, dynamic>> dashboard() => getJson('/dashboard');

  static Future<Map<String, dynamic>> myStay() => getJson('/me/stay');

  static Future<Map<String, dynamic>> me() => getJson('/me');

  static Future<Map<String, dynamic>> rooms({int page = 1}) =>
      getJson('/rooms', {'page': '$page'});

  static Future<Map<String, dynamic>> guests(
          {String search = '', int page = 1}) =>
      getJson('/guests', {'search': search, 'page': '$page'});

  static Future<Map<String, dynamic>> bookings(
          {bool live = false, int page = 1}) =>
      getJson('/bookings', {if (live) 'live': '1', 'page': '$page'});

  static Future<Map<String, dynamic>> invoices(
          {String status = '', int page = 1}) =>
      getJson('/invoices', {'status': status, 'page': '$page'});

  static Future<Map<String, dynamic>> complaints({int page = 1}) =>
      getJson('/complaints', {'page': '$page'});

  static Future<Map<String, dynamic>> inquiries(
          {String status = '', int page = 1}) =>
      getJson('/inquiries', {'status': status, 'page': '$page'});

  static Future<Map<String, dynamic>> visitors({bool insideOnly = false}) =>
      getJson('/visitors', {if (insideOnly) 'inside': '1'});

  static Future<void> addVisitor({
    required String name,
    String? phone,
    int? guestId,
    String? relation,
    String? purpose,
    bool overnight = false,
  }) async {
    await postJson('/visitors', {
      'visitor_name': name,
      if (phone != null && phone.isNotEmpty) 'visitor_phone': phone,
      'guest_id': ?guestId,
      if (relation != null && relation.isNotEmpty) 'relation': relation,
      if (purpose != null && purpose.isNotEmpty) 'purpose': purpose,
      'is_overnight': overnight,
    }, fallback: 'Could not log visitor.');
  }

  static Future<void> checkoutVisitor(int id) async {
    await postJson('/visitors/$id/checkout', const {},
        fallback: 'Could not check out visitor.');
  }

  static Future<void> addLead({
    required String name,
    required String phone,
    String source = 'walk_in',
    double? budget,
    String? message,
  }) async {
    await postJson('/inquiries', {
      'name': name,
      'phone': phone,
      'source': source,
      'budget': ?budget,
      if (message != null && message.isNotEmpty) 'message': message,
    }, fallback: 'Could not save lead.');
  }

  /// Logs the reminder on the server and returns the wa.me link to open.
  static Future<String> remindInvoice(int invoiceId) async {
    final body = await postJson('/invoices/$invoiceId/remind', const {},
        fallback: 'Could not prepare reminder.');
    return '${body['url'] ?? ''}';
  }

  /// Razorpay payment link for an invoice balance (online payments).
  static Future<String> payLink(int invoiceId) async {
    final body = await postJson('/invoices/$invoiceId/pay-link', const {},
        fallback: 'Online payment is not available right now.');
    return '${body['url'] ?? ''}';
  }

  /// POST helper: any 2xx is success, anything else throws [ApiException]
  /// with the server's own message.
  static Future<Map<String, dynamic>> postJson(
      String path, Map<String, dynamic> payload,
      {String fallback = 'Request failed.'}) async {
    final res = await post(path, payload);
    final body = decode(res);
    await _handleAuthFailure(res, body);
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw ApiException(messageOf(body, fallback));
    }
    return body is Map<String, dynamic> ? body : {'data': body};
  }

  static Future<Map<String, dynamic>> collect(
      {required int invoiceId,
      required double amount,
      required String method,
      String? transactionId,
      String? notes}) async {
    return postJson('/invoices/$invoiceId/collect', {
      'amount': amount,
      'payment_method': method,
      if (transactionId != null && transactionId.isNotEmpty)
        'transaction_id': transactionId,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    }, fallback: 'Could not record payment.');
  }

  static Future<Map<String, dynamic>> raiseComplaint(
      {required String title,
      String? description,
      String category = 'other',
      String priority = 'medium'}) async {
    return postJson('/complaints', {
      'title': title,
      if (description != null && description.isNotEmpty)
        'description': description,
      'category': category,
      'priority': priority,
    }, fallback: 'Could not raise complaint.');
  }

  static Future<Map<String, dynamic>> getJson(String path,
      [Map<String, String>? query]) async {
    final res = await get(path, query);
    final body = decode(res);
    await _handleAuthFailure(res, body);
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw ApiException(messageOf(body, 'Request failed.'));
    }
    if (body is Map<String, dynamic>) return body;
    return {'data': body};
  }

  /// 401 = token gone; 403 "disabled" = the owner switched this login off.
  /// Both end the session locally and send the user back to sign-in.
  static Future<void> _handleAuthFailure(http.Response res, dynamic body) async {
    final disabled = res.statusCode == 403 &&
        messageOf(body, '').toLowerCase().contains('disabled');
    if (res.statusCode != 401 && !disabled) return;
    await _clearSession();
    onSessionEnded?.call();
    throw ApiException(disabled
        ? 'This login has been disabled. Please contact the PG office.'
        : 'Session expired. Please sign in again.');
  }

  static Future<void> _clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_roleKey);
    await prefs.remove(_nameKey);
  }

  static dynamic decode(http.Response res) {
    try {
      return jsonDecode(res.body);
    } catch (_) {
      return {'message': res.body};
    }
  }

  static String messageOf(dynamic body, String fallback) {
    if (body is Map) {
      if (body['message'] is String &&
          (body['message'] as String).isNotEmpty) {
        return body['message'] as String;
      }
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = (errors.values.first as List?)?.first;
        if (first != null) return first.toString();
      }
    }
    return fallback;
  }

  static List<Map<String, dynamic>> pageItems(dynamic decoded) {
    if (decoded is List) {
      return decoded.whereType<Map<String, dynamic>>().toList();
    }
    if (decoded is Map<String, dynamic>) {
      final data = decoded['data'];
      if (data is List) {
        return data.whereType<Map<String, dynamic>>().toList();
      }
    }
    return const [];
  }

  static double numOf(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse('${value ?? ''}') ?? 0;
  }
}

class LoginResult {
  final String role;
  final String name;
  const LoginResult({required this.role, required this.name});
}

class Session {
  final String role;
  final String name;
  const Session({required this.role, required this.name});

  bool get isTenant => role == 'tenant';

  bool get isStaff => !isTenant;

  /// Owner and manager see money beyond one bill (P&L, expenses).
  bool get canManageFinance => role == 'admin' || role == 'manager';

  String get roleLabel => switch (role) {
        'admin' => 'Owner',
        'manager' => 'Manager',
        'staff' => 'Warden',
        'tenant' => 'Resident',
        _ => 'Staff',
      };
}

class ApiException implements Exception {
  final String message;
  const ApiException(this.message);

  @override
  String toString() => message;
}
