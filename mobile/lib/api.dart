import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'models.dart';

class ShopApi {
  final http.Client client;
  final Uri base;
  ShopApi({
    http.Client? client,
    String url = const String.fromEnvironment(
      'DOSMART_BASE_URL',
      defaultValue: 'https://dosmart.aican.cloud',
    ),
  }) : client = client ?? http.Client(),
       base = Uri.parse(url) {
    if (base.scheme != 'https' ||
        base.host.isEmpty ||
        base.userInfo.isNotEmpty ||
        base.hasQuery ||
        base.hasFragment) {
      throw ArgumentError('Требуется HTTPS-адрес магазина');
    }
  }
  Future<dynamic> request(String path, {Map<String, dynamic>? body}) async {
    try {
      final req =
          http.Request(body == null ? 'GET' : 'POST', base.resolve(path))
            ..followRedirects = false
            ..headers['Accept'] = 'application/json';
      if (body != null) {
        req.headers['Content-Type'] = 'application/json';
        req.body = jsonEncode(body);
      }
      final res = await (() async => http.Response.fromStream(
        await client.send(req),
      ))().timeout(const Duration(seconds: 25));
      if (res.statusCode < 200 || res.statusCode >= 300) {
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
      (await request('/api/v1/categories') as List)
          .map((j) => ShopCategory.fromJson(j))
          .toList();
  Future<List<ShopInstitution>> institutions() async =>
      (await request('/api/v1/institutions') as List)
          .map((j) => ShopInstitution.fromJson(j))
          .toList();
  Future<ShopSubmission> submit(Map<String, dynamic> body) async {
    try {
      return ShopSubmission.fromJson(
        await request('/api/v1/guest/orders', body: body),
      );
    } on ShopException {
      rethrow;
    } catch (_) {
      throw const ShopException(
        'Не удалось прочитать подтверждение. Повторите отправку.',
      );
    }
  }

  void close() => client.close();
}
