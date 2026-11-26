import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import 'models.dart';

class ShopApi {
  final http.Client client;
  final Uri base;
  // Токен привязки планшета к учреждению; отправляется только в кабинет.
  String? tabletToken;
  // Ключ кабинета (--dart-define=DOSMART_GATEWAY). Без ключа сборка работает как раньше:
  // каталог и заявки идут, кабинет закрыт. DOSMART_TEST_GATEWAY — старое имя для тестового стенда.
  static const testGateway = String.fromEnvironment(
    'DOSMART_GATEWAY',
    defaultValue: String.fromEnvironment('DOSMART_TEST_GATEWAY'),
  );
  ShopApi({
    http.Client? client,
    String url = const String.fromEnvironment(
      'DOSMART_BASE_URL',
      defaultValue: 'https://dos-mart.kz',
    ),
  }) : client = client ?? http.Client(),
       base = Uri.parse(url) {
    final localDebug =
        kDebugMode &&
        base.scheme == 'http' &&
        const ['localhost', '127.0.0.1'].contains(base.host);
    if ((base.scheme != 'https' && !localDebug) ||
        base.host.isEmpty ||
        base.userInfo.isNotEmpty ||
        base.hasQuery ||
        base.hasFragment) {
      throw ArgumentError('Требуется HTTPS-адрес магазина');
    }
  }
  Future<dynamic> request(
    String path, {
    Map<String, dynamic>? body,
    String? token,
  }) async {
    try {
      final req =
          http.Request(body == null ? 'GET' : 'POST', base.resolve(path))
            ..followRedirects = false
            ..headers['Accept'] = 'application/json';
      if (token != null) req.headers['Authorization'] = 'Bearer $token';
      final cabinet =
          path.startsWith('/api/v1/prisoner/') ||
          path.startsWith('/api/v1/tablet');
      if (cabinet && tabletToken != null) {
        req.headers['X-Dosmart-Tablet'] = tabletToken!;
      }
      if (cabinet && testGateway.isNotEmpty) {
        req.headers['X-Dosmart-Tablet-Gateway'] = testGateway;
      }
      if (body != null) {
        req.headers['Content-Type'] = 'application/json';
        req.body = jsonEncode(body);
      }
      final res = await (() async => http.Response.fromStream(
        await client.send(req),
      ))().timeout(const Duration(seconds: 25));
      if (res.statusCode < 200 || res.statusCode >= 300) {
        if (cabinet) {
          String? serverMessage;
          Object? data;
          try {
            data = jsonDecode(utf8.decode(res.bodyBytes));
            if (data is Map &&
                data['message'] is String &&
                (data['message'] as String).isNotEmpty) {
              serverMessage = data['message'] as String;
            }
          } catch (_) {}
          if (res.statusCode == 422 && data is Map && data['errors'] is Map) {
            final first = (data['errors'] as Map).values.firstOrNull;
            if (first is List && first.isNotEmpty && first.first is String) {
              throw ShopException(first.first as String, 422);
            }
          }
          if (res.statusCode == 401 && token != null) {
            throw const ShopException(
              'Сеанс истёк. Войдите снова для продолжения.',
              401,
            );
          }
          if ([401, 403, 404, 409, 423, 428].contains(res.statusCode)) {
            throw ShopException(
              serverMessage ??
                  switch (res.statusCode) {
                    403 => 'Кабинет доступен только на планшете учреждения.',
                    423 => 'Вход временно заблокирован.',
                    428 => 'Планшет не привязан к учреждению.',
                    404 =>
                      'Человек не найден. Проверьте ФИО и условия содержания.',
                    _ => 'Проверьте данные или обратитесь к сотруднику.',
                  },
              res.statusCode,
            );
          }
        }
        throw ShopException(switch (res.statusCode) {
          422 =>
            'Проверьте данные. Возможно, товар или учреждение больше недоступны.',
          429 => 'Слишком много запросов. Подождите минуту и повторите.',
          409 =>
            'Эта попытка уже связана с другой заявкой. Обратитесь к сотруднику магазина.',
          404 => 'На сервере ещё не включён приём заявок из приложения.',
          _ =>
            'Не удалось получить подтверждение магазина. Повторите отправку.',
        }, res.statusCode);
      }
      return jsonDecode(utf8.decode(res.bodyBytes));
    } on ShopException {
      rethrow;
    } catch (_) {
      throw const ShopException(
        'Нет подтверждения от сервера. Проверьте интернет и повторите отправку.',
      );
    }
  }

  Future<List<ShopProduct>> products() async =>
      (await request('/api/v1/products') as List)
          .map((j) => ShopProduct.fromJson(j))
          .toList();
  Future<List<ShopCategory>> categories() async =>
      ShopCategory.fromTree(await request('/api/v1/categories') as List);
  Future<List<ShopInstitution>> institutions() async =>
      (await request('/api/v1/institutions') as List)
          .map((j) => ShopInstitution.fromJson(j))
          .toList();

  /// [token] — сессия кабинета: сервер проверит норму передач получателя.
  Future<ShopSubmission> submit(
    Map<String, dynamic> body, {
    String? token,
  }) async {
    try {
      return ShopSubmission.fromJson(
        await request('/api/v1/guest/orders', body: body, token: token),
      );
    } on ShopException {
      rethrow;
    } catch (_) {
      throw const ShopException(
        'Не удалось прочитать подтверждение. Повторите отправку.',
      );
    }
  }

  Future<Map<String, dynamic>> _post(
    String path,
    Map<String, dynamic> body, [
    String? token,
  ]) async =>
      await request(path, body: body, token: token) as Map<String, dynamic>;

  Future<TabletInfo> setupTablet(String code) async {
    final data = await _post('/api/v1/tablet/setup', {'code': code});
    final institution = data['institution'] as Map<String, dynamic>;
    return TabletInfo(
      data['token'] as String,
      (institution['id'] as num).toInt(),
      institution['name'] as String,
    );
  }

  Future<void> checkTablet() async => request('/api/v1/tablet');

  Future<List<String>> squads() async =>
      ((await request('/api/v1/prisoner/squads') as Map)['squads'] as List)
          .map((s) => s.toString())
          .toList();

  WalletLogin _session(Map<String, dynamic> data) => WalletLogin(
    data['token'] as String,
    WalletProfile.fromJson(data['profile'] as Map<String, dynamic>),
  );

  /// Регистрация: full_name, condition, squad, birth_date, pin, pin_confirmation, consent.
  Future<WalletLogin> register(Map<String, dynamic> body) async =>
      _session(await _post('/api/v1/prisoner/register', body));

  /// Вход: ФИО + дата рождения + пароль.
  Future<WalletLogin> login(
    String name,
    String birthDate,
    String secret,
  ) async => _session(
    await _post('/api/v1/prisoner/login', {
      'full_name': name,
      if (birthDate.isNotEmpty) 'birth_date': birthDate,
      'secret': secret,
    }),
  );

  Future<WalletProfile> changeCredentials(
    String token,
    String current,
    Map<String, dynamic> credentials,
  ) async => WalletProfile.fromJson(
    (await _post('/api/v1/prisoner/credentials', {
          'current': current,
          ...credentials,
        }, token))['profile']
        as Map<String, dynamic>,
  );

  /// Забыл пароль: ФИО + дата рождения (+ ответ на вопрос, если выбран он) + новый пароль.
  Future<WalletLogin> recover(
    String name,
    String birthDate,
    String proof,
    String pin,
    String pin2,
  ) async => _session(
    await _post('/api/v1/prisoner/recover', {
      'full_name': name,
      'birth_date': birthDate,
      if (proof.isNotEmpty) 'proof': proof,
      'pin': pin,
      'pin_confirmation': pin2,
    }),
  );

  /// Отряд меняется в настройках (для доставки, не для входа).
  Future<WalletProfile> setSquad(String token, String squad) async =>
      WalletProfile.fromJson(
        (await _post('/api/v1/prisoner/squad', {
              'squad': squad,
            }, token))['profile']
            as Map<String, dynamic>,
      );

  /// Способ восстановления: method = birth_date | question (+question, answer).
  Future<WalletProfile> recoverySettings(
    String token,
    String current,
    Map<String, dynamic> body,
  ) async => WalletProfile.fromJson(
    (await _post('/api/v1/prisoner/recovery', {
          'current': current,
          ...body,
        }, token))['profile']
        as Map<String, dynamic>,
  );

  Future<String> familyCode(String token) async =>
      (await _post('/api/v1/prisoner/family-code', {}, token))['code']
          as String;

  Future<void> disableFamilyCode(String token) async =>
      _post('/api/v1/prisoner/family-code/disable', {}, token);

  Future<({WalletProfile profile, List<WalletOperation> operations})> history(
    String token,
  ) async {
    final data =
        await request('/api/v1/prisoner/history', token: token)
            as Map<String, dynamic>;
    return (
      profile: WalletProfile.fromJson(data['profile'] as Map<String, dynamic>),
      operations: (data['operations'] as List)
          .map((j) => WalletOperation.fromJson(j as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<WalletReceipt> purchase(
    Map<String, dynamic> body,
    String token,
  ) async {
    final data =
        await request('/api/v1/prisoner/purchases', body: body, token: token)
            as Map<String, dynamic>;
    return WalletReceipt(
      data['order_number'] as String,
      data['total_minor'] as int,
    );
  }

  void close() => client.close();
}
