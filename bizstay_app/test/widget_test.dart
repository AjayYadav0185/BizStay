// BizStay smoke + unit tests: app boots to login, helpers behave.
import 'package:bizstay_app/api_service.dart';
import 'package:bizstay_app/links.dart';
import 'package:bizstay_app/main.dart';
import 'package:bizstay_app/screens/tenant_shell.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('App boots to BizStay login', (tester) async {
    SharedPreferences.setMockInitialValues(const {});
    await tester.pumpWidget(const BizStayApp());
    await tester.pumpAndSettle();
    expect(find.text('Welcome back'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });

  test('pageItems unwraps Laravel paginators and plain lists', () {
    expect(ApiService.pageItems({'data': [{'id': 1}]}), hasLength(1));
    expect(ApiService.pageItems([{'id': 1}, {'id': 2}]), hasLength(2));
    expect(ApiService.pageItems({'data': []}), isEmpty);
    expect(ApiService.pageItems(null), isEmpty);
  });

  test('numOf parses int/double/string safely', () {
    expect(ApiService.numOf(10), 10.0);
    expect(ApiService.numOf('99.5'), 99.5);
    expect(ApiService.numOf(null), 0.0);
    expect(ApiService.numOf('abc'), 0.0);
  });

  test('Session role helper', () {
    expect(const Session(role: 'tenant', name: 'A').isTenant, isTrue);
    expect(const Session(role: 'manager', name: 'B').isTenant, isFalse);
  });

  test('Roles: owner/manager see finance, wardens do not', () {
    const owner = Session(role: 'admin', name: 'O');
    const warden = Session(role: 'staff', name: 'W');
    expect(owner.isStaff, isTrue);
    expect(owner.canManageFinance, isTrue);
    expect(owner.roleLabel, 'Owner');
    expect(warden.isStaff, isTrue);
    expect(warden.canManageFinance, isFalse);
    expect(warden.roleLabel, 'Warden');
    expect(const Session(role: 'tenant', name: 'T').isStaff, isFalse);
  });

  test('WhatsApp links use the Indian country code', () {
    expect(Links.whatsapp('98765 43210'), 'https://wa.me/919876543210');
    expect(Links.whatsapp('09876543210'), 'https://wa.me/919876543210');
    expect(Links.whatsapp('+91 98765-43210', 'Hi there'),
        'https://wa.me/919876543210?text=Hi%20there');
  });

  Widget payCard({required bool online}) => MaterialApp(
        home: Scaffold(
          body: PayCard(
            invoiceId: 7,
            invoiceNumber: 'BZ-202611-0007',
            amount: 9000,
            dueDate: '2026-11-05',
            upiUri: 'upi://pay?pa=pg%40okhdfc&am=9000.00',
            payLink: null,
            onlinePayments: online,
            upiId: 'pg@okhdfc',
          ),
        ),
      );

  testWidgets('Pay card offers UPI apps when online payments are off',
      (tester) async {
    await tester.pumpWidget(payCard(online: false));
    expect(find.text('Pay with GPay / PhonePe / Paytm'), findsOneWidget);
    expect(find.text('Pay online (UPI / card)'), findsNothing);
    expect(find.text('UPI ID: pg@okhdfc'), findsOneWidget);
  });

  testWidgets('Pay card leads with the online link when enabled',
      (tester) async {
    await tester.pumpWidget(payCard(online: true));
    expect(find.text('Pay online (UPI / card)'), findsOneWidget);
    expect(find.text('Pay with UPI app'), findsOneWidget);
  });
}
