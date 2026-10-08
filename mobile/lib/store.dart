import 'dart:math';
import 'package:flutter/foundation.dart';
import 'api.dart';
import 'models.dart';

class Store extends ChangeNotifier {
  final ShopApi api;
  Store(this.api);
  List<ShopProduct> products = [];
  List<ShopCategory> categories = [];
  List<ShopInstitution> institutions = [];
  final Map<int, int> cart = {};
  bool loading = false, sending = false;
  String? error;
  Map<String, dynamic>? _pending;
  ShopSubmission? sent;
  bool get frozen => sending || _pending != null;
  int get count => cart.values.fold(0, (a, b) => a + b);
  List<ShopCartLine> get lines => products
      .where((p) => cart.containsKey(p.id))
      .map((p) => ShopCartLine(p, cart[p.id]!))
      .toList();
  int get total => lines.fold(0, (a, b) => a + b.totalMinor);
  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final result = await Future.wait([
        api.products(),
        api.categories(),
        api.institutions(),
      ]);
      products = result[0] as List<ShopProduct>;
      categories = result[1] as List<ShopCategory>;
      institutions = result[2] as List<ShopInstitution>;
    } catch (_) {
      error =
          'Не удалось загрузить каталог. Проверьте соединение и попробуйте снова.';
    }
    loading = false;
    notifyListeners();
  }

  void change(ShopProduct p, int delta) {
    if (frozen) return;
    final n = ((cart[p.id] ?? 0) + delta).clamp(0, 1000);
    if (n == 0) {
      cart.remove(p.id);
    } else if (cart.containsKey(p.id) || cart.length < 100) {
      cart[p.id] = n;
    }
    notifyListeners();
  }

  static String uuid() {
    final r = Random.secure();
    final b = List.generate(16, (_) => r.nextInt(256));
    b[6] = (b[6] & 15) | 64;
    b[8] = (b[8] & 63) | 128;
    final s = b.map((v) => v.toRadixString(16).padLeft(2, '0')).join();
    return '${s.substring(0, 8)}-${s.substring(8, 12)}-${s.substring(12, 16)}-${s.substring(16, 20)}-${s.substring(20)}';
  }

  Future<bool> submit(String name, int institution, String phone) async {
    if (sending || cart.isEmpty) return false;
    _pending ??= {
      'request_id': uuid(),
      'items': lines.map((l) => l.toJson()).toList(),
      'prisoner_name': name.trim(),
      'institution_id': institution,
      'contact_phone': phone.trim(),
      'consent': true,
    };
    sending = true;
    error = null;
    notifyListeners();
    try {
      sent = await api.submit(_pending!);
      cart.clear();
      _pending = null;
    } on ShopException catch (e) {
      error = e.message;
      // Only definite rejections allow editing; a timeout retries the same UUID/body.
      if ([422, 429, 404].contains(e.status)) _pending = null;
    }
    sending = false;
    notifyListeners();
    return sent != null;
  }

  void reset() {
    cart.clear();
    _pending = null;
    sent = null;
    error = null;
    notifyListeners();
  }

  @override
  void dispose() {
    api.close();
    super.dispose();
  }
}
