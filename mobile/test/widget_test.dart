import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shop/api.dart';
import 'package:shop/main.dart';
import 'package:shop/models.dart';
import 'package:shop/store.dart';
import 'package:shop/tablet_storage.dart';

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

  test(
    'balance purchase retries the same request after session renewal',
    () async {
      final purchases = <String>[];
      var logins = 0;
      final json = {'content-type': 'application/json; charset=utf-8'};
      final store = Store(
        ShopApi(
          client: MockClient((request) async {
            if (request.url.path.endsWith('/login')) {
              logins++;
              final body = jsonDecode(request.body) as Map;
              expect(body['full_name'], 'Тестовый Получатель');
              expect(body['birth_date'], '1990-03-15');
              expect(body['secret'], '482915');
              return http.Response(
                jsonEncode({
                  'token': 'session-$logins',
                  'profile': {
                    'full_name': 'Тестовый Получатель',
                    'squad': '7',
                    'balance_minor': 900000,
                    'delivery_minor': 300000,
                    'monthly_remaining_minor': null,
                    'parcels_available': 2,
                  },
                }),
                200,
                headers: json,
              );
            }
            expect(request.url.path, '/api/v1/prisoner/purchases');
            expect(request.headers['Authorization'], 'Bearer session-$logins');
            purchases.add(request.body);
            return switch (purchases.length) {
              1 => throw Exception('confirmation lost'),
              2 => http.Response('{}', 401),
              _ => http.Response(
                jsonEncode({'order_number': '0110001', 'total_minor': 380000}),
                201,
              ),
            };
          }),
        ),
      );
      Future<void> signIn() async {
        expect(
          await store.signIn('Тестовый Получатель', '1990-03-15', '482915'),
          true,
        );
      }

      const product = ShopProduct(id: 1, name: 'Чай', priceMinor: 80000);
      store.products = [product];
      store.change(product, 1);
      await signIn();
      expect(await store.purchase(), false);
      expect(await store.purchase(), false);
      expect(store.walletSignedIn, false);
      await signIn();
      expect(logins, 2);
      expect(await store.purchase(), true);
      expect(purchases.toSet(), hasLength(1));
      final sent = jsonDecode(purchases.first) as Map;
      expect(sent['expected_total_minor'], 380000);
      expect(sent['squad_number'], '7');
      expect(store.wallet, null);
      expect(store.walletReceipt?.totalMinor, 380000);
      expect(store.count, 0);
      store.dispose();
    },
  );

  testWidgets('unbound tablet asks staff for the institution code', (
    tester,
  ) async {
    final storage = MemoryTabletStorage();
    final store = Store(
      ShopApi(
        client: MockClient((r) async {
          if (r.url.path == '/api/v1/tablet/setup') {
            expect(jsonDecode(r.body), {'code': '12345678'});
            return http.Response.bytes(
              utf8.encode(
                jsonEncode({
                  'token': 'tablet-token',
                  'institution': {'id': 3, 'name': 'ИК-3'},
                }),
              ),
              200,
            );
          }
          return http.Response('[]', 200);
        }),
      ),
      storage: storage,
    );
    await tester.pumpWidget(DoSmartApp(store: store));
    await tester.pumpAndSettle();
    final open = find.text('Войти в личный кабинет');
    await tester.ensureVisible(open);
    await tester.tap(open);
    await tester.pumpAndSettle();
    expect(find.text('Планшет не привязан к учреждению'), findsOneWidget);
    await tester.tap(find.text('Настройка планшета (для сотрудника)'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), '12345678');
    await tester.tap(find.text('Привязать планшет'));
    await tester.pumpAndSettle();
    expect(storage.value?.institutionName, 'ИК-3');
    expect(store.api.tabletToken, 'tablet-token');
    expect(find.text('Нужные вещи для вас'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    store.dispose();
  });

  testWidgets(
    'prisoner registers without search (name, conditions, squad, birth date, 6-digit password) and sees the cabinet',
    (tester) async {
      Map? registered;
      final store = Store(
        ShopApi(
          client: MockClient((r) async {
            final json = {'content-type': 'application/json; charset=utf-8'};
            Object data = [];
            if (r.url.path.startsWith('/api/v1/prisoner') ||
                r.url.path.startsWith('/api/v1/tablet')) {
              expect(r.headers['X-Dosmart-Tablet'], 'tab');
            }
            final profile = {
              'full_name': 'Иванов Иван',
              'institution': 'ИК-1',
              'squad': '7',
              'condition': 'Обычные',
              'balance_minor': 5000000,
              'delivery_minor': 300000,
              'monthly_limit_minor': 2595000,
              'limit_carried_minor': 500000,
              'monthly_spent_minor': 0,
              'monthly_remaining_minor': 3095000,
              'auth_method': 'pin',
              'family_code_active': false,
            };
            switch (r.url.path) {
              case '/api/v1/tablet':
                data = {
                  'institution': {'id': 1, 'name': 'ИК-1'},
                };
              case '/api/v1/prisoner/squads':
                data = {
                  'squads': ['7', '10'],
                };
              case '/api/v1/prisoner/register':
                registered = jsonDecode(r.body) as Map;
                data = {'token': 't1', 'profile': profile};
              case '/api/v1/prisoner/history':
                data = {
                  'profile': profile,
                  'operations': [
                    {
                      'kind': 'deposit',
                      'title': 'Пополнение от родственника: Иванова Мария',
                      'amount_minor': 5000000,
                      'at': '12.11.2026 10:00',
                    },
                  ],
                };
              case '/api/v1/prisoner/family-code':
                data = {'code': '482 915'};
            }
            return http.Response(jsonEncode(data), 200, headers: json);
          }),
        ),
        storage: MemoryTabletStorage(const TabletInfo('tab', 1, 'ИК-1')),
      );
      await tester.pumpWidget(DoSmartApp(store: store));
      await tester.pumpAndSettle();
      final open = find.text('Войти в личный кабинет');
      await tester.ensureVisible(open);
      await tester.tap(open);
      await tester.pumpAndSettle();
      expect(find.text('Вход'), findsOneWidget);
      expect(find.text('Найти себя'), findsNothing);
      final toRegister = find.widgetWithText(OutlinedButton, 'Регистрация');
      await tester.ensureVisible(toRegister);
      await tester.tap(toRegister);
      await tester.pumpAndSettle();
      expect(find.text('Регистрация'), findsOneWidget);
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'Иванов Иван');
      final conditions = find.byType(DropdownButtonFormField<String>);
      await tester.ensureVisible(conditions);
      await tester.pumpAndSettle();
      await tester.tap(conditions);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Обычные').last);
      await tester.pumpAndSettle();
      await tester.enterText(fields.at(1), '7');
      final submit = find.text('Зарегистрироваться');
      // Неверная дата не уходит на сервер; точки ставятся сами.
      await tester.enterText(fields.at(2), '31021990');
      expect(find.text('31.02.1990'), findsOneWidget);
      await tester.ensureVisible(submit);
      await tester.tap(submit);
      await tester.pumpAndSettle();
      expect(find.text('Проверьте дату рождения: ДД.ММ.ГГГГ.'), findsOneWidget);
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();
      await tester.enterText(fields.at(2), '15031990');
      // Пароль вводится один раз.
      await tester.enterText(fields.at(3), '482915');
      await tester.ensureVisible(submit);
      await tester.tap(submit);
      await tester.pumpAndSettle();
      expect(registered, isNull);
      expect(find.text('Отметьте согласие.'), findsOneWidget);
      // Ждём, пока подсказка внизу экрана исчезнет.
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();
      final consent = find.byType(Checkbox);
      await tester.ensureVisible(consent);
      await tester.tap(consent);
      await tester.pumpAndSettle();
      await tester.ensureVisible(submit);
      await tester.tap(submit);
      await tester.pumpAndSettle();
      expect(registered, {
        'consent': true,
        'full_name': 'Иванов Иван',
        'condition': 'ordinary',
        'squad': '7',
        'birth_date': '1990-03-15',
        'pin': '482915',
        'pin_confirmation': '482915',
      });
      expect(find.text('Купить со счёта'), findsOneWidget);
      expect(find.text('Заказать через родственника'), findsOneWidget);
      expect(find.text('Настройки'), findsOneWidget);
      expect(find.text('Выйти'), findsOneWidget);
      expect(find.textContaining(RegExp(r'^30\s950 ₸$')), findsOneWidget);
      expect(find.textContaining('Доставка в лимит не входит'), findsOneWidget);
      expect(find.textContaining('перенос'), findsOneWidget);
      expect(
        find.text('Пополнение от родственника: Иванова Мария'),
        findsOneWidget,
      );
      await tester.tap(find.text('Настройки'));
      await tester.pumpAndSettle();
      // Отдельного кода для родственников больше нет — только заказ через корзину.
      expect(find.text('Получить код'), findsNothing);
      expect(find.text('Код для родственников'), findsNothing);
      await tester.ensureVisible(find.text('Назад в кабинет'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Назад в кабинет'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Выйти'));
      await tester.pumpAndSettle();
      expect(store.walletSignedIn, false);
      expect(find.text('Нужные вещи для вас'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
      store.dispose();
    },
  );
  testWidgets(
    'forgotten password is restored by birth date and a new 6-digit password',
    (tester) async {
      Map? recovered;
      final profile = {
        'full_name': 'Иванов Иван',
        'institution': 'ИК-1',
        'condition': 'Обычные',
        'balance_minor': 0,
        'delivery_minor': 300000,
        'auth_method': 'pin',
        'recovery_method': 'birth_date',
        'birth_date_set': true,
      };
      final store = Store(
        ShopApi(
          client: MockClient((r) async {
            final json = {'content-type': 'application/json; charset=utf-8'};
            Object data = {};
            switch (r.url.path) {
              case '/api/v1/tablet':
                data = {
                  'institution': {'id': 1, 'name': 'ИК-1'},
                };
              case '/api/v1/prisoner/squads':
                data = {'squads': []};
              case '/api/v1/prisoner/recover':
                recovered = jsonDecode(r.body) as Map;
                data = {'token': 't1', 'profile': profile};
              case '/api/v1/prisoner/history':
                data = {'profile': profile, 'operations': []};
            }
            return http.Response(jsonEncode(data), 200, headers: json);
          }),
        ),
        storage: MemoryTabletStorage(const TabletInfo('tab', 1, 'ИК-1')),
      );
      await tester.pumpWidget(DoSmartApp(store: store));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Войти в личный кабинет'));
      await tester.tap(find.text('Войти в личный кабинет'));
      await tester.pumpAndSettle();
      expect(find.text('Пароль'), findsOneWidget);
      final forgot = find.widgetWithText(TextButton, 'Забыли пароль?');
      await tester.ensureVisible(forgot);
      await tester.pumpAndSettle();
      await tester.tap(forgot);
      await tester.pumpAndSettle();
      expect(find.text('Новый пароль'), findsOneWidget);
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'Иванов Иван');
      await tester.enterText(fields.at(1), '15031990');
      await tester.enterText(fields.at(2), '735102');
      final save = find.text('Сохранить и войти');
      await tester.ensureVisible(save);
      await tester.pumpAndSettle();
      await tester.tap(save);
      await tester.pumpAndSettle();
      expect(recovered, {
        'full_name': 'Иванов Иван',
        'birth_date': '1990-03-15',
        'pin': '735102',
        'pin_confirmation': '735102',
      });
      expect(find.text('Купить со счёта'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
      store.dispose();
    },
  );
  testWidgets('login asks only name and password, birth date only on request', (
    tester,
  ) async {
    final logins = <Map>[];
    final profile = {
      'full_name': 'Иванов Иван',
      'institution': 'ИК-1',
      'condition': 'Обычные',
      'balance_minor': 0,
      'delivery_minor': 300000,
      'auth_method': 'pin',
    };
    final store = Store(
      ShopApi(
        client: MockClient((r) async {
          final json = {'content-type': 'application/json; charset=utf-8'};
          Object data = {};
          var status = 200;
          switch (r.url.path) {
            case '/api/v1/tablet':
              data = {
                'institution': {'id': 1, 'name': 'ИК-1'},
              };
            case '/api/v1/prisoner/squads':
              data = {'squads': []};
            case '/api/v1/prisoner/login':
              final body = jsonDecode(r.body) as Map;
              logins.add(body);
              if (!body.containsKey('birth_date')) {
                status = 409;
                data = {'message': 'Есть тёзка.'};
              } else {
                data = {'token': 't1', 'profile': profile};
              }
            case '/api/v1/prisoner/history':
              data = {'profile': profile, 'operations': []};
          }
          return http.Response(jsonEncode(data), status, headers: json);
        }),
      ),
      storage: MemoryTabletStorage(const TabletInfo('tab', 1, 'ИК-1')),
    );
    await tester.pumpWidget(DoSmartApp(store: store));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Войти в личный кабинет'));
    await tester.tap(find.text('Войти в личный кабинет'));
    await tester.pumpAndSettle();
    expect(find.text('Дата рождения'), findsNothing);
    final fields = find.byType(TextField);
    await tester.enterText(fields.at(0), 'Иванов Иван');
    await tester.enterText(fields.at(1), '111111');
    final enter = find.widgetWithText(FilledButton, 'Войти');
    await tester.ensureVisible(enter);
    await tester.tap(enter);
    await tester.pumpAndSettle();
    expect(logins.single, {'full_name': 'Иванов Иван', 'secret': '111111'});
    expect(find.text('Укажите ещё дату рождения.'), findsOneWidget);
    expect(find.text('Дата рождения'), findsOneWidget);
    await tester.enterText(fields.at(1), '15031990');
    await tester.enterText(fields.at(2), '111111');
    await tester.ensureVisible(enter);
    await tester.tap(enter);
    await tester.pumpAndSettle();
    expect(logins.last, {
      'full_name': 'Иванов Иван',
      'birth_date': '1990-03-15',
      'secret': '111111',
    });
    expect(find.text('Купить со счёта'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    store.dispose();
  });
  testWidgets('instructions lead to catalog and an obvious basket button', (
    tester,
  ) async {
    final store = Store(
      ShopApi(client: MockClient((r) async => http.Response('[]', 200))),
    );
    await tester.pumpWidget(DoSmartApp(store: store));
    await tester.pumpAndSettle();
    expect(find.text('Узнайте WhatsApp родственника'), findsOneWidget);
    expect(find.text('Доставка — 3 000 ₸'), findsOneWidget);
    final start = find.text('Каталог — заказ через родственника');
    await tester.ensureVisible(start);
    await tester.tap(start);
    await tester.pumpAndSettle();
    expect(find.textContaining('Перейти в корзину'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    store.dispose();
  });
  testWidgets(
    'nested category filters include descendants and work with search',
    (tester) async {
      final store = Store(
        ShopApi(
          client: MockClient((r) async {
            Object data = [];
            if (r.url.path.endsWith('/products')) {
              data = [
                {'id': 1, 'name': 'Мука', 'price': 500, 'category_id': 2},
                {'id': 2, 'name': 'Спагетти', 'price': 800, 'category_id': 3},
                {'id': 3, 'name': 'Вода', 'price': 200, 'category_id': 4},
              ];
            }
            if (r.url.path.endsWith('/categories')) {
              data = [
                {
                  'id': 1,
                  'name': 'Бакалея',
                  'children': [
                    {
                      'id': 2,
                      'name': 'Мучное',
                      'children': [
                        {'id': 3, 'name': 'Макароны', 'children': []},
                      ],
                    },
                  ],
                },
                {'id': 4, 'name': 'Напитки'},
              ];
            }
            return http.Response.bytes(utf8.encode(jsonEncode(data)), 200);
          }),
        ),
      );
      await tester.pumpWidget(DoSmartApp(store: store));
      await tester.pumpAndSettle();
      await tester.ensureVisible(
        find.text('Каталог — заказ через родственника'),
      );
      await tester.tap(find.text('Каталог — заказ через родственника'));
      await tester.pumpAndSettle();
      Future<void> select(String label) async {
        await tester.tap(find.byType(DropdownButtonFormField<int>));
        await tester.pumpAndSettle();
        await tester.tap(find.text(label).last);
        await tester.pumpAndSettle();
      }

      await select('Бакалея / Мучное');
      expect(
        find.descendant(of: find.byType(GridView), matching: find.text('Мука')),
        findsOneWidget,
      );
      expect(find.text('Спагетти'), findsOneWidget);
      expect(find.text('Вода'), findsNothing);
      await select('Бакалея');
      expect(
        find.descendant(of: find.byType(GridView), matching: find.text('Мука')),
        findsOneWidget,
      );
      expect(find.text('Спагетти'), findsOneWidget);
      await tester.enterText(find.byType(TextField).first, 'Мука');
      await tester.pump(const Duration(milliseconds: 200));
      expect(
        find.descendant(of: find.byType(GridView), matching: find.text('Мука')),
        findsOneWidget,
      );
      expect(find.text('Спагетти'), findsNothing);
      await tester.enterText(find.byType(TextField).first, '');
      await tester.pump(const Duration(milliseconds: 200));
      await select('Напитки');
      expect(find.text('Вода'), findsOneWidget);
      expect(find.text('Мука'), findsNothing);
      await select('Все товары');
      expect(
        find.descendant(of: find.byType(GridView), matching: find.text('Мука')),
        findsOneWidget,
      );
      await tester.pumpWidget(const SizedBox());
      store.dispose();
    },
  );
  testWidgets(
    'checkout requires details and sends the selected institution and WhatsApp',
    (tester) async {
      Map<String, dynamic>? submitted;
      final store = Store(
        ShopApi(
          client: MockClient((r) async {
            Object data = [];
            if (r.url.path.endsWith('/products')) {
              data = [
                {'id': 1, 'name': 'Чай', 'price': 800},
              ];
            }
            if (r.url.path.endsWith('/institutions')) {
              data = [
                {'id': 7, 'name': 'Учреждение №7', 'city': 'Алматы'},
              ];
            }
            if (r.url.path.endsWith('/orders')) {
              submitted = jsonDecode(r.body) as Map<String, dynamic>;
              data = {
                'order_number': 'DOS-ABCDEFGH',
                'display_order_number': '0710483',
                'subtotal': '800.00',
              };
            }
            return http.Response.bytes(utf8.encode(jsonEncode(data)), 200);
          }),
        ),
      );
      await tester.pumpWidget(DoSmartApp(store: store));
      await tester.pumpAndSettle();
      final start = find.text('Каталог — заказ через родственника');
      await tester.ensureVisible(start);
      await tester.tap(start);
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'Нет такого товара');
      await tester.pump(const Duration(milliseconds: 200));
      expect(find.text('Товары не найдены'), findsOneWidget);
      await tester.enterText(find.byType(TextField).first, 'Чай');
      await tester.pump(const Duration(milliseconds: 200));
      tester.testTextInput.hide();
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('В корзину'));
      await tester.pumpAndSettle();
      final gridBefore = tester.widget<GridView>(find.byType(GridView));
      final cardBefore = tester.widget<Card>(find.byType(Card).first);
      await tester.tap(find.text('В корзину'));
      await tester.pump();
      expect(
        identical(cardBefore, tester.widget<Card>(find.byType(Card).first)),
        isTrue,
      );
      expect(
        identical(gridBefore, tester.widget<GridView>(find.byType(GridView))),
        isTrue,
      );
      expect(find.text('Корзина (1)'), findsOneWidget);
      expect(find.text('1'), findsOneWidget);
      await tester.tap(find.byTooltip('Добавить одну штуку'));
      await tester.pump();
      expect(store.count, 2);
      expect(find.text('2'), findsOneWidget);
      await tester.tap(find.byTooltip('Убрать одну штуку'));
      await tester.pump();
      expect(store.count, 1);
      await tester.tap(find.textContaining('Перейти в корзину'));
      await tester.pumpAndSettle();
      expect(find.text('Доставка — 3 000 ₸'), findsOneWidget);
      final fields = find.byType(TextFormField);
      await tester.ensureVisible(fields.at(0));
      await tester.enterText(fields.at(0), 'Иванов Иван Иванович');
      await tester.ensureVisible(fields.at(1));
      await tester.enterText(fields.at(1), '7');
      final dropdown = find.byType(DropdownButtonFormField<int>);
      await tester.pumpAndSettle();
      await tester.ensureVisible(dropdown);
      await tester.pumpAndSettle();
      await tester.tap(dropdown);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Учреждение №7 — Алматы').last);
      await tester.pumpAndSettle();
      await tester.ensureVisible(fields.at(2));
      await tester.enterText(fields.at(2), '+77011234567');
      final consent = find.byType(CheckboxListTile);
      await tester.ensureVisible(consent);
      await tester.tap(consent);
      await tester.pump();
      final submit = find.text('Отправить заявку в магазин');
      await tester.ensureVisible(submit);
      await tester.tap(submit);
      await tester.pumpAndSettle();
      expect(submitted?['institution_id'], 7);
      expect(submitted?['squad_number'], '7');
      expect(submitted?['contact_phone'], '+77011234567');
      expect(find.text('Заявка отправлена в магазин'), findsOneWidget);
      expect(find.text('Номер: 0710483'), findsOneWidget);
      expect(find.textContaining('через Soilephone'), findsOneWidget);
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
