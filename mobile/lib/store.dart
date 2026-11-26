import 'dart:math';

import 'package:flutter/foundation.dart';

import 'api.dart';
import 'models.dart';
import 'tablet_storage.dart';

class Store extends ChangeNotifier {
  final ShopApi api;
  final TabletStorage tabletStorage;
  Store(this.api, {TabletStorage? storage})
    : tabletStorage = storage ?? MemoryTabletStorage();
  List<ShopProduct> products = [];
  List<ShopCategory> categories = [];
  List<ShopInstitution> institutions = [];
  final Map<int, int> cart = {};
  bool loading = false, sending = false;
  String? error;
  Map<String, dynamic>? _pending;
  ShopSubmission? sent;
  WalletProfile? wallet;
  WalletReceipt? walletReceipt;
  String? _walletToken;
  List<WalletOperation> walletHistory = [];
  bool historyLoading = false;
  bool get walletSignedIn => _walletToken != null;
  // Учреждение планшета и его отряды.
  TabletInfo? tablet;
  List<String> squads = [];
  // Код для родственников показывается один раз сразу после выдачи.
  String? familyCode;
  String? notice;
  bool get frozen => sending || _pending != null;
  int get count => cart.values.fold(0, (a, b) => a + b);
  List<ShopCartLine> get lines => products
      .where((p) => cart.containsKey(p.id))
      .map((p) => ShopCartLine(p, cart[p.id]!))
      .toList();
  int get total => lines.fold(0, (a, b) => a + b.totalMinor);
  Future<void> load() async {
    if (loading || frozen) return;
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
    final previous = cart[p.id] ?? 0;
    final n = (previous + delta).clamp(0, 1000);
    if (n == previous || (previous == 0 && n > 0 && cart.length >= 100)) {
      return;
    }
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

  Future<bool> submit(
    String name,
    int institution,
    String phone, {
    String squadNumber = '',
  }) async {
    if (sending || cart.isEmpty) return false;
    _pending ??= {
      'request_id': uuid(),
      'items': lines.map((l) => l.toJson()).toList(),
      'prisoner_name': name.trim(),
      'squad_number': squadNumber.trim(),
      'institution_id': institution,
      'contact_phone': phone.trim(),
      'consent': true,
    };
    sending = true;
    error = null;
    notifyListeners();
    try {
      sent = await api.submit(_pending!, token: _walletToken);
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

  /// Код учреждения, зашитый в сборку для планшетов одного учреждения (Kora MDM).
  /// Пусто в обычной сборке — тогда сотрудник вводит код вручную.
  static const builtInTabletCode = String.fromEnvironment(
    'DOSMART_TABLET_CODE',
  );

  Future<void> loadTablet() async {
    tablet = await tabletStorage.load();
    api.tabletToken = tablet?.token;
    notifyListeners();
    if (tablet != null) {
      try {
        await api.checkTablet();
      } on ShopException catch (e) {
        // Код учреждения сменили в админке — нужна повторная настройка.
        if (e.status == 428 || e.status == 403) await _forgetTablet();
      } catch (_) {}
    }
    await autoSetupTablet();
  }

  /// Привязка без участия сотрудника, если код зашит в сборку. Ошибки не показываем:
  /// попробуем снова при следующем открытии кабинета.
  Future<void> autoSetupTablet() async {
    if (tablet != null || builtInTabletCode.isEmpty || sending) return;
    final ok = await setupTablet(builtInTabletCode);
    if (!ok) {
      error = null;
      errorStatus = null;
      notifyListeners();
    }
  }

  Future<void> _forgetTablet() async {
    tablet = null;
    api.tabletToken = null;
    await tabletStorage.save(null);
    notifyListeners();
  }

  /// HTTP-код последней ошибки (409 — нужен ещё и день рождения).
  int? errorStatus;

  Future<bool> _run(Future<void> Function() action) async {
    if (sending) return false;
    sending = true;
    error = null;
    errorStatus = null;
    notice = null;
    notifyListeners();
    var ok = false;
    try {
      await action();
      ok = true;
    } on ShopException catch (e) {
      error = e.message;
      errorStatus = e.status;
      if (e.status == 428) await _forgetTablet();
      if (e.status == 401 && _walletToken != null) clearWallet();
    } catch (_) {
      error = 'Нет связи с сервером. Проверьте интернет и повторите.';
    } finally {
      sending = false;
      notifyListeners();
    }
    return ok;
  }

  Future<bool> setupTablet(String code) => _run(() async {
    final info = await api.setupTablet(code.trim());
    tablet = info;
    api.tabletToken = info.token;
    await tabletStorage.save(info);
    squads = [];
  });

  Future<void> loadSquads() async {
    if (tablet == null) return;
    try {
      squads = await api.squads();
      notifyListeners();
    } on ShopException catch (e) {
      if (e.status == 428) await _forgetTablet();
    } catch (_) {}
  }

  void _signedIn(WalletLogin session) {
    wallet = session.profile;
    _walletToken = session.token;
  }

  /// Регистрация без поиска: ФИО, условия, отряд, дата рождения, пароль, согласие.
  Future<bool> register(Map<String, dynamic> body) =>
      _run(() async => _signedIn(await api.register(body)));

  Future<bool> signIn(String name, String birthDate, String secret) => _run(
    () async =>
        _signedIn(await api.login(name.trim(), birthDate, secret.trim())),
  );

  Future<bool> changeCredentials(
    String current,
    Map<String, dynamic> credentials,
  ) => _walletToken == null
      ? Future.value(false)
      : _run(() async {
          wallet = await api.changeCredentials(
            _walletToken!,
            current.trim(),
            credentials,
          );
          notice = 'Новый пароль сохранён.';
        });

  /// Забыл пароль: ФИО + дата рождения (+ ответ на вопрос) + новый пароль.
  Future<bool> recover(
    String name,
    String birthDate,
    String proof,
    String pin,
    String pin2,
  ) => _run(
    () async => _signedIn(
      await api.recover(name.trim(), birthDate, proof.trim(), pin, pin2),
    ),
  );

  Future<bool> setSquad(String squad) => _walletToken == null
      ? Future.value(false)
      : _run(() async {
          wallet = await api.setSquad(_walletToken!, squad.trim());
          notice = 'Отряд сохранён.';
        });

  Future<bool> setRecovery(String current, Map<String, dynamic> body) =>
      _walletToken == null
      ? Future.value(false)
      : _run(() async {
          wallet = await api.recoverySettings(
            _walletToken!,
            current.trim(),
            body,
          );
          notice = 'Способ восстановления пароля сохранён.';
        });

  Future<bool> issueFamilyCode() => _walletToken == null
      ? Future.value(false)
      : _run(() async {
          familyCode = await api.familyCode(_walletToken!);
          await loadHistory();
        });

  Future<bool> disableFamilyCode() => _walletToken == null
      ? Future.value(false)
      : _run(() async {
          await api.disableFamilyCode(_walletToken!);
          familyCode = null;
          notice = 'Код для родственников отключён.';
          await loadHistory();
        });

  Future<void> loadHistory() async {
    final token = _walletToken;
    if (token == null || historyLoading) return;
    historyLoading = true;
    notifyListeners();
    try {
      final result = await api.history(token);
      wallet = result.profile;
      walletHistory = result.operations;
    } on ShopException catch (e) {
      error = e.message;
      if (e.status == 401) clearWallet();
    } catch (_) {
      error = 'Не удалось загрузить историю операций.';
    } finally {
      historyLoading = false;
      notifyListeners();
    }
  }

  Future<bool> purchase([String? squadOverride]) async {
    final squad = squadOverride ?? wallet?.squad ?? '';
    final token = _walletToken;
    final profile = wallet;
    if (sending || cart.isEmpty || token == null || profile == null) {
      return false;
    }
    _pending ??= {
      'request_id': uuid(),
      'items': lines.map((line) => line.toJson()).toList(),
      'expected_total_minor': total + profile.deliveryMinor,
      'squad_number': squad.trim(),
      'consent': true,
    };
    sending = true;
    error = null;
    notifyListeners();
    try {
      walletReceipt = await api.purchase(_pending!, token);
      cart.clear();
      _pending = null;
      clearWallet();
    } on ShopException catch (e) {
      error = e.message;
      if (e.status == 401) _walletToken = null;
      if (e.status == 422) _pending = null;
    } catch (_) {
      error = 'Подтверждение не получено. Повторите отправку.';
    } finally {
      sending = false;
      notifyListeners();
    }
    return walletReceipt != null;
  }

  void clearWallet() {
    wallet = null;
    walletHistory = [];
    _walletToken = null;
    familyCode = null;
    notice = null;
    error = null;
    notifyListeners();
  }

  void reset() {
    cart.clear();
    _pending = null;
    sent = null;
    walletReceipt = null;
    clearWallet();
  }

  @override
  void dispose() {
    api.close();
    super.dispose();
  }
}
