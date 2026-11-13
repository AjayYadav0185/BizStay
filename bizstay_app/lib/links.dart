import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

/// Opens UPI apps, WhatsApp, Razorpay links and the dialer.
class Links {
  Links._();

  /// Opens [url] outside the app. Shows [failMessage] when nothing on the
  /// phone can handle it (e.g. no UPI app installed). Returns true on success.
  static Future<bool> open(BuildContext context, String url,
      {String failMessage = 'No app on this phone can open this link.'}) async {
    final uri = Uri.tryParse(url);
    var ok = false;
    if (uri != null && url.isNotEmpty) {
      try {
        ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
      } catch (_) {
        ok = false;
      }
    }
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(failMessage)));
    }
    return ok;
  }

  static Future<bool> call(BuildContext context, String phone) =>
      open(context, 'tel:${phone.replaceAll(RegExp(r'[^0-9+]'), '')}',
          failMessage: 'Could not open the dialer.');

  /// wa.me link for an Indian number with an optional pre-typed message.
  static String whatsapp(String phone, [String message = '']) {
    var digits = phone.replaceAll(RegExp(r'\D'), '');
    if (digits.length == 10) digits = '91$digits';
    if (digits.length == 11 && digits.startsWith('0')) {
      digits = '91${digits.substring(1)}';
    }
    final text = message.isEmpty ? '' : '?text=${Uri.encodeComponent(message)}';
    return 'https://wa.me/$digits$text';
  }
}
