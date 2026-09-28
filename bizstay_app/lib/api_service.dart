import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Talks to the BizStay Laravel API (routes/api.php, Sanctum tokens).
class ApiService {
  ApiService._();

  static const _tokenKey = 'bizstay_token';
  static const _roleKey = 'bizstay_role';
  static const _nameKey = 'bizstay_name';
  static const _baseUrlKey = 'bizstay_base_url';

  static const String defaultBaseUrl = String.fromEnvironment(
    'BIZSTAY_API_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
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
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_roleKey);
    await prefs.remove(_nameKey);
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

  static Future<Map<String, dynamic>> collect(
      {required int invoiceId,
      required double amount,
      required String method,
      String? transactionId,
      String? notes}) async {
    final res = await post('/invoices/$invoiceId/collect', {
      'amount': amount,
      'payment_method': method,
      if (transactionId != null && transactionId.isNotEmpty)
        'transaction_id': transactionId,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    });
    final body = decode(res);
    if (res.statusCode != 201) {
      throw ApiException(messageOf(body, 'Could not record payment.'));
    }
    return body is Map<String, dynamic> ? body : {'data': body};
  }

  static Future<Map<String, dynamic>> raiseComplaint(
      {required String title,
      String? description,
      String category = 'other',
      String priority = 'medium'}) async {
    final res = await post('/complaints', {
      'title': title,
      if (description != null && description.isNotEmpty)
        'description': description,
      'category': category,
      'priority': priority,
    });
    final body = decode(res);
    if (res.statusCode != 201) {
      throw ApiException(messageOf(body, 'Could not raise complaint.'));
    }
    return body is Map<String, dynamic> ? body : {'data': body};
  }

  static Future<Map<String, dynamic>> getJson(String path,
      [Map<String, String>? query]) async {
    final res = await get(path, query);
    final body = decode(res);
    if (res.statusCode == 401) {
      await logout();
      throw const ApiException('Session expired. Please sign in again.');
    }
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw ApiException(messageOf(body, 'Request failed.'));
    }
    if (body is Map<String, dynamic>) return body;
    return {'data': body};
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
}

class ApiException implements Exception {
  final String message;
  const ApiException(this.message);

  @override
  String toString() => message;
}
