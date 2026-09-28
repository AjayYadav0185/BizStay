// BizStay smoke + unit tests: app boots to login, helpers behave.
import 'package:bizstay_app/api_service.dart';
import 'package:bizstay_app/main.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('App boots to BizStay login', (tester) async {
    SharedPreferences.setMockInitialValues(const {});
    await tester.pumpWidget(const BizStayApp());
    await tester.pumpAndSettle();
    expect(find.text('Welcome to BizStay'), findsOneWidget);
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
}

