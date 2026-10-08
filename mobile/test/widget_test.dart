import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shop/api.dart';
import 'package:shop/main.dart';
import 'package:shop/models.dart';
import 'package:shop/store.dart';

void main() {
  test(
    'unknown result retries the identical guest request and clears PII on success',
    () async {
      final requests = <String>[];
      final store = Store(
        ShopApi(
          client: MockClient((r) async {
            expect(r.url.path, '/api/v1/guest/orders');
            expect(r.headers.containsKey('Authorization'), false);
            requests.add(r.body);
            if (requests.length == 1) throw Exception('connection lost');
            return http.Response(
              jsonEncode({
                'order_number': 'DOS-ABCDEFGH',
                'subtotal': '800.00',
              }),
              201,
            );
          }),
        ),
      );
      const p = ShopProduct(id: 1, name: 'Чай', priceMinor: 80000);
      store.products = [p];
      store.change(p, 1);
      expect(await store.submit('Иванов Иван', 1, '+77011234567'), false);
      expect(store.frozen, true);
      store.change(p, 1);
      expect(store.count, 1);
      expect(await store.submit('Иванов Иван', 1, '+77011234567'), true);
      expect(requests[0], requests[1]);
      expect(store.count, 0);
      expect(store.frozen, false);
      store.reset();
      expect(store.sent, null);
      store.dispose();
    },
  );

  testWidgets('instructions lead to catalog and an obvious basket button', (
    tester,
  ) async {
    final store = Store(
      ShopApi(client: MockClient((r) async => http.Response('[]', 200))),
    );
    await tester.pumpWidget(DoSmartApp(store: store));
    await tester.pumpAndSettle();
    expect(find.text('Узнайте WhatsApp родственника'), findsOneWidget);
    final start = find.text('Понятно, перейти в магазин');
    await tester.ensureVisible(start);
    await tester.tap(start);
    await tester.pumpAndSettle();
    expect(find.textContaining('Перейти в корзину'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    store.dispose();
  });
  testWidgets(
    'checkout requires details and sends the selected institution and WhatsApp',
    (tester) async {
      Map<String, dynamic>? submitted;
      final store = Store(
        ShopApi(
          client: MockClient((r) async {
            Object data = [];
            if (r.url.path.endsWith('/products'))
              data = [
                {'id': 1, 'name': 'Чай', 'price': 800},
              ];
            if (r.url.path.endsWith('/institutions'))
              data = [
                {'id': 7, 'name': 'Учреждение №7', 'city': 'Алматы'},
              ];
            if (r.url.path.endsWith('/orders')) {
              submitted = jsonDecode(r.body) as Map<String, dynamic>;
              data = {'order_number': 'DOS-ABCDEFGH', 'subtotal': '800.00'};
            }
            return http.Response.bytes(utf8.encode(jsonEncode(data)), 200);
          }),
        ),
      );
      await tester.pumpWidget(DoSmartApp(store: store));
      await tester.pumpAndSettle();
      final start = find.text('Понятно, перейти в магазин');
      await tester.ensureVisible(start);
      await tester.tap(start);
      await tester.pumpAndSettle();
      await tester.tap(find.text('В корзину'));
      await tester.pump();
      await tester.tap(find.textContaining('Перейти в корзину'));
      await tester.pumpAndSettle();
      final fields = find.byType(TextFormField);
      await tester.ensureVisible(fields.at(0));
      await tester.enterText(fields.at(0), 'Иванов Иван Иванович');
      final dropdown = find.byType(DropdownButtonFormField<int>);
      await tester.ensureVisible(dropdown);
      await tester.tap(dropdown);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Учреждение №7 — Алматы').last);
      await tester.pumpAndSettle();
      await tester.ensureVisible(fields.at(1));
      await tester.enterText(fields.at(1), '+77011234567');
      final consent = find.byType(CheckboxListTile);
      await tester.ensureVisible(consent);
      await tester.tap(consent);
      await tester.pump();
      final submit = find.text('Отправить заявку в магазин');
      await tester.ensureVisible(submit);
      await tester.tap(submit);
      await tester.pumpAndSettle();
      expect(submitted?['institution_id'], 7);
      expect(submitted?['contact_phone'], '+77011234567');
      expect(find.text('Заявка отправлена в магазин'), findsOneWidget);
      expect(find.text('Иванов Иван Иванович'), findsNothing);
      expect(store.count, 0);
      await tester.pump(const Duration(minutes: 5));
      await tester.pumpAndSettle();
      expect(find.text('Узнайте WhatsApp родственника'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
      store.dispose();
    },
  );
}
