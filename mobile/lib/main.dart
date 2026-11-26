import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart' show ScrollCacheExtent;
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart';

import 'api.dart';
import 'models.dart';
import 'store.dart';
import 'phone_formatter.dart';
import 'tablet_storage.dart';

const ink = Color(0xFF123F75);
const brandGreen = Color(0xFF287C4A);
const softGreen = Color(0xFFE7F3E9);
final _moneyFormat = NumberFormat('#,##0.##', 'ru');
String money(int value) => '${_moneyFormat.format(value / 100)} ₸';
void main() {
  WidgetsFlutterBinding.ensureInitialized();
  // Keep decoded catalogue photos bounded on low-memory kiosk tablets.
  PaintingBinding.instance.imageCache.maximumSizeBytes = 32 * 1024 * 1024;
  PaintingBinding.instance.imageCache.maximumSize = 80;
  runApp(const DoSmartApp());
}

class DoSmartApp extends StatelessWidget {
  const DoSmartApp({super.key, this.store});
  final Store? store;
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Dosmart',
    debugShowCheckedModeBanner: false,
    locale: const Locale('ru'),
    supportedLocales: const [Locale('ru')],
    localizationsDelegates: GlobalMaterialLocalizations.delegates,
    theme: ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: ink).copyWith(
        primary: ink,
        secondary: brandGreen,
        secondaryContainer: softGreen,
      ),
      scaffoldBackgroundColor: const Color(0xFFF2F6FA),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: ink,
        centerTitle: false,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: brandGreen,
          foregroundColor: Colors.white,
          minimumSize: const Size(48, 52),
          textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    home: ShopHome(store: store),
  );
}

class ShopHome extends StatefulWidget {
  const ShopHome({super.key, this.store});
  final Store? store;
  @override
  State<ShopHome> createState() => _ShopHomeState();
}

class _ShopHomeState extends State<ShopHome> with WidgetsBindingObserver {
  late final Store store;
  final name = TextEditingController(),
      phone = TextEditingController(),
      squad = TextEditingController(),
      search = TextEditingController(),
      accName = TextEditingController(),
      accSquad = TextEditingController(),
      squadEdit = TextEditingController(),
      accDay = TextEditingController(),
      accMonth = TextEditingController(),
      accYear = TextEditingController(),
      accSecret = TextEditingController(),
      accPin = TextEditingController(),
      accPin2 = TextEditingController(),
      accAnswer = TextEditingController(),
      curSecret = TextEditingController(),
      newPin = TextEditingController(),
      newPin2 = TextEditingController(),
      newAnswer = TextEditingController(),
      recProof = TextEditingController(),
      recDay = TextEditingController(),
      recMonth = TextEditingController(),
      recYear = TextEditingController(),
      recPin = TextEditingController(),
      recPin2 = TextEditingController(),
      setCurrent = TextEditingController(),
      setDay = TextEditingController(),
      setMonth = TextEditingController(),
      setYear = TextEditingController(),
      tabletCode = TextEditingController();
  late final List<TextEditingController> _accountFields = [
    accName,
    accSquad,
    squadEdit,
    accDay,
    accMonth,
    accYear,
    accSecret,
    accPin,
    accPin2,
    accAnswer,
    curSecret,
    newPin,
    newPin2,
    newAnswer,
    recProof,
    recDay,
    recMonth,
    recYear,
    recPin,
    recPin2,
    setCurrent,
    setDay,
    setMonth,
    setYear,
    tabletCode,
  ];
  String? accCondition;
  bool regConsent = false;
  // Показать пароль; спросить дату при входе (тёзки); поле ответа на вопрос.
  bool showPin = false, loginNeedsDate = false, recShowProof = false;
  // Экран кабинета без входа: login | register | recover.
  String accMode = 'login';
  String setRecovery = 'birth_date', setQuestion = 'grandmother';
  final form = GlobalKey<FormState>();
  // 0 инструкция, 1 каталог, 2 корзина, 3 готово, 4 кабинет, 5 настройки, 6 настройка планшета.
  int page = 0;
  int? institution, category;
  bool consent = false, walletMode = false;
  Timer? idle;
  Timer? searchDebounce;
  Object? _lastStoreView;
  final Map<ShopProduct, Widget> _productCards = {};
  List<ShopProduct>? _catalogSource;
  List<ShopProduct> _visibleProducts = [];
  String? _filterQuery;
  int? _filterCategory;
  DateTime lastActivity = DateTime.now();
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    store = widget.store ?? Store(ShopApi(), storage: FileTabletStorage());
    store.addListener(refresh);
    store.load();
    store.loadTablet();
    touch();
  }

  void refresh() {
    final view = (
      store.loading,
      store.error,
      store.products,
      store.categories,
      store.institutions,
      store.frozen,
      store.sending,
      store.sent,
      store.wallet,
      store.walletHistory,
      store.tablet,
      store.squads,
      store.familyCode,
      store.notice,
      store.historyLoading,
      store.error,
    );
    final unchanged = view == _lastStoreView;
    _lastStoreView = view;
    // Basket counters listen separately: changing a quantity need not rebuild
    // the search field, category strip and the entire scrolling catalog.
    if (mounted && !(page == 1 && unchanged)) setState(() {});
  }

  void touch() {
    lastActivity = DateTime.now();
    idle?.cancel();
    idle = Timer(const Duration(minutes: 5), () {
      if (store.sending) {
        touch();
      } else {
        reset();
      }
    });
  }

  void reset() {
    searchDebounce?.cancel();
    name.clear();
    phone.clear();
    squad.clear();
    search.clear();
    for (final c in _accountFields) {
      c.clear();
    }
    accCondition = null;
    setRecovery = 'birth_date';
    regConsent = false;
    showPin = loginNeedsDate = recShowProof = false;
    accMode = 'login';
    walletMode = false;
    institution = null;
    category = null;
    consent = false;
    page = 0;
    store.reset();
    touch();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      if (!store.sending &&
          DateTime.now().difference(lastActivity) >=
              const Duration(minutes: 5)) {
        reset();
      } else {
        touch();
      }
    }
  }

  @override
  void dispose() {
    idle?.cancel();
    searchDebounce?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    store.removeListener(refresh);
    if (widget.store == null) store.dispose();
    name.dispose();
    phone.dispose();
    squad.dispose();
    search.dispose();
    for (final c in _accountFields) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> finish() async {
    if (store.sending) return;
    if (page == 0 || page == 3) {
      reset();
      await SystemNavigator.pop();
      return;
    }
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Завершить сеанс?'),
        content: Text(
          store.frozen
              ? 'Подтверждение заявки не получено. Она могла поступить в магазин. Лучше повторить отправку — повтор не создаст новую заявку. При выходе данные на планшете будут очищены.'
              : 'Корзина и введённые данные будут очищены на этом планшете.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Продолжить'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Завершить'),
          ),
        ],
      ),
    );
    if (yes == true && mounted) {
      reset();
      await SystemNavigator.pop();
    }
  }

  /// Учреждение планшета, если оно есть в списке активных.
  int? get tabletInstitution {
    final id = store.tablet?.institutionId;
    return id != null && store.institutions.any((i) => i.id == id) ? id : null;
  }

  Future<void> send() async {
    final target = tabletInstitution ?? institution;
    if (!form.currentState!.validate() || !consent || target == null) {
      return;
    }
    final ok = await store.submit(
      name.text,
      target,
      '+7${PhoneFormatter.digits(phone.text)}',
      squadNumber: squad.text,
    );
    if (mounted && ok) {
      name.clear();
      phone.clear();
      squad.clear();
      institution = null;
      consent = false;
      setState(() => page = 3);
      touch();
    }
  }

  Widget text(String value, {double size = 16, bool bold = false}) => Text(
    value,
    style: TextStyle(
      color: ink,
      fontSize: size,
      fontWeight: bold ? FontWeight.w600 : FontWeight.normal,
      height: 1.4,
    ),
  );
  Widget panel(Widget child) => Container(
    padding: const EdgeInsets.all(24),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(24),
    ),
    child: child,
  );
  Widget centered(List<Widget> children) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 660),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: children,
        ),
      ),
    ),
  );
  Widget step(String number, String title, String body) => Padding(
    padding: const EdgeInsets.only(bottom: 22),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CircleAvatar(
          backgroundColor: softGreen,
          foregroundColor: ink,
          child: Text(number),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              text(title, bold: true, size: 19),
              const SizedBox(height: 4),
              text(body),
            ],
          ),
        ),
      ],
    ),
  );
  Widget intro() => centered([
    // The supplied logo has large white margins. Clip only the presentation,
    // keeping the original asset intact and the complete wordmark visible.
    GestureDetector(
      // Скрытый вход в настройку планшета для сотрудника.
      onLongPress: () => setState(() => page = 6),
      child: SizedBox(
        height: 170,
        child: FittedBox(
          fit: BoxFit.contain,
          child: ClipRect(
            child: Align(
              widthFactor: .64,
              heightFactor: .34,
              alignment: const Alignment(-.03, -.03),
              child: Image.asset(
                'assets/dosmart.png',
                width: 1024,
                height: 1024 * 2 / 3,
                cacheWidth: 512,
                semanticLabel: 'Dosmart',
              ),
            ),
          ),
        ),
      ),
    ),
    const SizedBox(height: 20),
    text('Нужные вещи для вас', size: 32, bold: true),
    const SizedBox(height: 12),
    text(
      'Соберите корзину. Вы сможете оплатить со счёта Dosmart или отправить заявку для оплаты родственником.',
    ),
    const SizedBox(height: 12),
    text('Доставка — 3 000 ₸', size: 20, bold: true),
    text('Заявки обрабатываются с 17:00 до 21:00 по времени Казахстана.'),
    const SizedBox(height: 20),
    // Первый выбор: войти в кабинет (счёт, лимит, покупка со счёта)
    // или сразу в каталог — заказ, который оплатит родственник.
    SizedBox(
      height: 72,
      child: FilledButton.icon(
        onPressed: openAccount,
        icon: const Icon(Icons.login, size: 28),
        label: const Text('Войти в личный кабинет'),
      ),
    ),
    const SizedBox(height: 12),
    SizedBox(
      height: 72,
      child: OutlinedButton.icon(
        style: OutlinedButton.styleFrom(
          foregroundColor: ink,
          backgroundColor: Colors.white,
          side: const BorderSide(color: ink, width: 1.5),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
        ),
        onPressed: openCatalog,
        icon: const Icon(Icons.storefront_outlined, size: 28),
        label: const Text('Каталог — заказ через родственника'),
      ),
    ),
    const SizedBox(height: 8),
    text(
      'В кабинете — ваш счёт и лимит, покупка со счёта и заказ через родственника. '
      'Без входа можно собрать заказ, который оплатит родственник.',
      size: 14,
    ),
    const SizedBox(height: 28),
    panel(
      Column(
        children: [
          step(
            '1',
            'Узнайте WhatsApp родственника',
            'Подготовьте правильный номер с кодом страны и предупредите родственника, что ему напишет магазин.',
          ),
          step(
            '2',
            'Выберите товары',
            'Добавьте нужные продукты и вещи в корзину. Укажите свои ФИО, учреждение и WhatsApp родственника.',
          ),
          step(
            '3',
            'Отправьте заявку',
            'Сотрудник согласует с родственником товары, доставку и оплату. На этом планшете оплачивать ничего не нужно.',
          ),
          text('Данные сеанса очищаются после 5 минут бездействия.', size: 13),
        ],
      ),
    ),
  ]);
  Widget quantity(ShopProduct p) => ListenableBuilder(
    listenable: store,
    builder: (_, _) => quantityControls(p),
  );

  Widget quantityControls(ShopProduct p) {
    final n = store.cart[p.id] ?? 0;
    if (n == 0) {
      return FilledButton(
        onPressed: store.frozen ? null : () => store.change(p, 1),
        child: const Text('В корзину'),
      );
    }
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        IconButton(
          tooltip: 'Убрать одну штуку',
          onPressed: store.frozen ? null : () => store.change(p, -1),
          icon: const Icon(Icons.remove_circle_outline),
        ),
        text('$n', bold: true),
        IconButton(
          tooltip: 'Добавить одну штуку',
          onPressed: store.frozen || n >= 1000
              ? null
              : () => store.change(p, 1),
          icon: const Icon(Icons.add_circle_outline),
        ),
      ],
    );
  }

  // Keep the image/text subtree stable when only basket quantities change.
  Widget product(ShopProduct p) => _productCards.putIfAbsent(
    p,
    () => Card(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: p.imageUrl == null
                  ? const Icon(
                      Icons.shopping_bag_outlined,
                      size: 60,
                      color: ink,
                    )
                  : Image(
                      image: ResizeImage(
                        NetworkImage(p.imageUrl!),
                        width: 320,
                        height: 320,
                        policy: ResizeImagePolicy.fit,
                      ),
                      fit: BoxFit.contain,
                      filterQuality: FilterQuality.low,
                      errorBuilder: (_, _, _) => const Icon(
                        Icons.shopping_bag_outlined,
                        size: 60,
                        color: ink,
                      ),
                    ),
            ),
            const SizedBox(height: 10),
            Text(
              p.name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: ink,
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            Text(p.unit ?? '', style: const TextStyle(color: Colors.grey)),
            const SizedBox(height: 8),
            text(money(p.priceMinor), bold: true, size: 19),
            const SizedBox(height: 8),
            quantity(p),
          ],
        ),
      ),
    ),
  );
  Widget catalog() {
    if (store.loading) return const Center(child: CircularProgressIndicator());
    if (store.products.isEmpty && store.error != null) {
      return centered([
        text(store.error!),
        const SizedBox(height: 20),
        FilledButton(
          onPressed: store.load,
          child: const Text('Повторить загрузку'),
        ),
      ]);
    }
    final query = search.text.trim().toLowerCase();
    if (!identical(_catalogSource, store.products)) {
      _productCards.clear();
      _filterQuery = null;
      _catalogSource = store.products;
    }
    if (_filterQuery != query || _filterCategory != category) {
      final selectedCategory = store.categories
          .where((c) => c.id == category)
          .firstOrNull;
      _visibleProducts = store.products
          .where(
            (p) =>
                (category == null ||
                    (selectedCategory?.includes(p.categoryId) ?? false)) &&
                p.name.toLowerCase().contains(query),
          )
          .toList();
      _filterQuery = query;
      _filterCategory = category;
    }
    final visible = _visibleProducts;
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
          child: TextField(
            controller: search,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => FocusScope.of(context).unfocus(),
            onChanged: (_) {
              searchDebounce?.cancel();
              searchDebounce = Timer(const Duration(milliseconds: 150), () {
                if (mounted) setState(() {});
              });
            },
            decoration: const InputDecoration(
              hintText: 'Найти товар',
              helperText: 'Доставка — 3 000 ₸',
              prefixIcon: Icon(Icons.search),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
          child: DropdownButtonFormField<int>(
            initialValue: category ?? 0,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Категория'),
            items: [
              const DropdownMenuItem(value: 0, child: Text('Все товары')),
              ...store.categories.map(
                (c) => DropdownMenuItem(
                  value: c.id,
                  child: Text(c.name, overflow: TextOverflow.ellipsis),
                ),
              ),
            ],
            onChanged: (value) {
              FocusScope.of(context).unfocus();
              setState(() => category = value == 0 ? null : value);
            },
          ),
        ),
        Expanded(
          child: visible.isEmpty
              ? Center(child: text('Товары не найдены'))
              : LayoutBuilder(
                  builder: (c, size) {
                    final columns = (size.maxWidth / 220).floor().clamp(1, 5);
                    return GridView.builder(
                      keyboardDismissBehavior:
                          ScrollViewKeyboardDismissBehavior.onDrag,
                      scrollCacheExtent: const ScrollCacheExtent.pixels(100),
                      padding: const EdgeInsets.all(20),
                      itemCount: visible.length,
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: columns,
                        mainAxisExtent: 330,
                        mainAxisSpacing: 16,
                        crossAxisSpacing: 16,
                      ),
                      itemBuilder: (_, i) => product(visible[i]),
                    );
                  },
                ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: () => setState(() => page = 2),
              icon: const Icon(Icons.shopping_basket_outlined),
              label: ListenableBuilder(
                listenable: store,
                builder: (_, _) => Text(
                  'Перейти в корзину (${store.count}) · ${money(store.total)}',
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget basket() {
    if (store.cart.isEmpty) {
      return centered([
        text('Корзина пока пуста', size: 28, bold: true),
        const SizedBox(height: 20),
        FilledButton(
          onPressed: () => setState(() => page = 1),
          child: const Text('Выбрать товары'),
        ),
      ]);
    }
    return centered([
      text('Ваша корзина', size: 28, bold: true),
      const SizedBox(height: 20),
      ...store.lines.map(
        (l) => Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: panel(
            Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                text(l.product.name, bold: true),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [text(money(l.totalMinor)), quantity(l.product)],
                ),
              ],
            ),
          ),
        ),
      ),
      text('Товары: ${money(store.total)}', size: 23, bold: true),
      if (!store.frozen)
        TextButton(
          onPressed: () {
            setState(() {
              walletMode = !walletMode;
              consent = false;
              squad.clear();
            });
          },
          child: Text(
            walletMode ? 'Оплатит родственник' : 'Купить со счёта Dosmart',
          ),
        ),
      if (walletMode)
        ...walletCheckout()
      else ...[
        if (store.walletSignedIn && store.wallet != null) ...relativeLimits(),
        text('Доставка — 3 000 ₸', size: 20, bold: true),
        text(
          'Окончательный состав, сумму и дату доставки сотрудник согласует с родственником.',
        ),
        const SizedBox(height: 24),
        Form(
          key: form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextFormField(
                controller: name,
                enabled: !store.frozen,
                maxLength: 255,
                decoration: const InputDecoration(
                  labelText: 'Ваши ФИО полностью',
                ),
                validator: (v) =>
                    (v?.trim().length ?? 0) < 3 ? 'Укажите ФИО' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: squad,
                enabled: !store.frozen,
                maxLength: 50,
                decoration: const InputDecoration(
                  labelText: 'Отряд',
                  hintText: 'Например, 7',
                ),
                validator: (v) =>
                    (v?.trim().isEmpty ?? true) ? 'Укажите отряд' : null,
              ),
              const SizedBox(height: 12),
              if (tabletInstitution != null)
                InputDecorator(
                  decoration: const InputDecoration(labelText: 'Учреждение'),
                  child: Text(store.tablet!.institutionName),
                )
              else
                DropdownButtonFormField<int>(
                  initialValue: institution,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Учреждение'),
                  items: store.institutions
                      .map(
                        (i) => DropdownMenuItem(
                          value: i.id,
                          child: Text(i.label, overflow: TextOverflow.ellipsis),
                        ),
                      )
                      .toList(),
                  onChanged: store.frozen
                      ? null
                      : (v) => setState(() => institution = v),
                  validator: (v) => v == null ? 'Выберите учреждение' : null,
                ),
              if (store.institutions.isEmpty)
                TextButton(
                  onPressed: store.frozen ? null : store.load,
                  child: const Text('Обновить список учреждений'),
                ),
              const SizedBox(height: 20),
              TextFormField(
                controller: phone,
                enabled: !store.frozen,
                keyboardType: TextInputType.phone,
                inputFormatters: [PhoneFormatter()],
                decoration: const InputDecoration(
                  labelText: 'WhatsApp родственника',
                  prefixText: '+7 ',
                  hintText: '777 621 8745',
                  helperText: 'Проверьте номер — на него напишет магазин',
                ),
                validator: (v) => PhoneFormatter.digits(v ?? '').length == 10
                    ? null
                    : 'Введите 10 цифр после +7',
              ),
              const SizedBox(height: 12),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                value: consent,
                onChanged: store.frozen
                    ? null
                    : (v) => setState(() => consent = v ?? false),
                title: const Text(
                  'Согласен на обработку введённых данных для оформления заявки и связи с родственником. Номер указан с его разрешения.',
                ),
              ),
              if (store.error != null)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Text(
                    store.error!,
                    style: const TextStyle(color: Colors.red, fontSize: 16),
                  ),
                ),
              if (store.frozen && !store.sending)
                text(
                  'При повторной отправке используем ту же заявку, чтобы не создавать дубликат.',
                  size: 14,
                ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: consent && !store.sending ? send : null,
                child: Text(
                  store.sending
                      ? 'Отправляем…'
                      : store.frozen
                      ? 'Повторить отправку'
                      : 'Отправить заявку в магазин',
                ),
              ),
              const SizedBox(height: 12),
              text(
                'Отправка заявки не означает оплату. Сначала сотрудник свяжется с родственником.',
                size: 14,
              ),
            ],
          ),
        ),
      ],
    ]);
  }

  /// Заказ через родственника из кабинета: показываем только лимиты, чтобы не заказать лишнего.
  List<Widget> relativeLimits() {
    final profile = store.wallet!;
    final remaining = profile.monthlyRemainingMinor;
    final over = remaining != null && store.total > remaining;
    return [
      const SizedBox(height: 8),
      panel(
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            text('Ваши лимиты', size: 18, bold: true),
            text(
              remaining == null
                  ? 'Месячный лимит покупок: без ограничения'
                  : 'Месячный лимит покупок: осталось ${money(remaining)}',
            ),
            if (profile.parcelsAvailable case final int parcels)
              text('Передач доступно в этом году: $parcels'),
            if (over)
              text(
                'Товаров в корзине больше, чем остаток лимита. Уберите лишнее, '
                'иначе учреждение может не принять передачу.',
                bold: true,
              ),
          ],
        ),
      ),
      const SizedBox(height: 16),
    ];
  }

  List<Widget> walletCheckout() {
    final profile = store.wallet;
    if (!store.walletSignedIn || profile == null) {
      return [
        text(
          'Чтобы купить со счёта, войдите в личный кабинет. '
          'Пополнить счёт можно заявлением в учреждении или через родственников.',
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: () => openAccount(),
          icon: const Icon(Icons.login),
          label: const Text('Войти в личный кабинет'),
        ),
        if (store.error != null) text(store.error!, bold: true),
      ];
    }
    final total = store.total + profile.deliveryMinor;
    final remaining = profile.monthlyRemainingMinor;
    final withinLimit = remaining == null || remaining >= store.total;
    final enough = profile.balanceMinor >= total && withinLimit;
    final needSquad = (profile.squad ?? '').isEmpty;
    return [
      text(profile.fullName, bold: true),
      text('Счёт: ${money(profile.balanceMinor)}'),
      text(
        remaining == null
            ? 'Лимит на покупки: без ограничения'
            : 'Остаток лимита на товары: ${money(remaining)}',
      ),
      text('Доставка: ${money(profile.deliveryMinor)} — в лимит не входит'),
      text('К списанию со счёта: ${money(total)}', bold: true),
      if (!enough && !store.frozen)
        text(
          withinLimit
              ? 'На счёте недостаточно денег для этой корзины.'
              : 'Товары в корзине превышают остаток лимита на этот месяц.',
        ),
      if (needSquad)
        TextField(
          controller: squad,
          enabled: !store.frozen,
          maxLength: 50,
          decoration: const InputDecoration(labelText: 'Отряд'),
          onChanged: (_) => setState(() {}),
        ),
      CheckboxListTile(
        value: consent,
        onChanged: store.frozen
            ? null
            : (value) => setState(() => consent = value ?? false),
        title: const Text(
          'Согласен на обработку данных для покупки и доставки.',
        ),
      ),
      FilledButton(
        onPressed:
            !store.sending &&
                (store.frozen ||
                    (enough &&
                        consent &&
                        (!needSquad || squad.text.trim().isNotEmpty)))
            ? () async {
                final ok = await store.purchase(needSquad ? squad.text : null);
                if (mounted && ok) {
                  squad.clear();
                  consent = false;
                  setState(() => page = 3);
                  touch();
                }
              }
            : null,
        child: Text(store.frozen ? 'Повторить отправку' : 'Оплатить со счёта'),
      ),
      if (store.error != null) text(store.error!, bold: true),
    ];
  }

  void openCatalog() {
    store.error = null;
    setState(() {
      walletMode = false;
      page = 1;
    });
  }

  void openAccount() {
    store.error = null;
    setState(() => page = 4);
    // Не было связи при запуске — привяжем планшет сейчас (если код зашит в сборку).
    if (store.tablet == null) {
      store.autoSetupTablet().then((_) => store.loadSquads());
    }
    if (store.walletSignedIn) {
      store.loadHistory();
    } else {
      store.loadSquads();
    }
  }

  void snack(String message) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(message)));

  Widget errorText() => store.error == null
      ? const SizedBox.shrink()
      : Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Text(
            store.error!,
            style: const TextStyle(color: Colors.red, fontSize: 16),
          ),
        );

  Widget pinField(TextEditingController c, String label, {int length = 12}) =>
      TextField(
        controller: c,
        enabled: !store.sending,
        keyboardType: TextInputType.number,
        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
        maxLength: length,
        obscureText: !showPin,
        style: const TextStyle(fontSize: 22, letterSpacing: 4),
        decoration: InputDecoration(
          labelText: label,
          counterText: '',
          suffixIcon: IconButton(
            tooltip: showPin ? 'Скрыть' : 'Показать',
            icon: Icon(showPin ? Icons.visibility_off : Icons.visibility),
            onPressed: () => setState(() => showPin = !showPin),
          ),
        ),
      );

  /// Дата рождения одним полем: цифры, точки ставятся сами (ДД.ММ.ГГГГ).
  Widget dateField(TextEditingController c, {String label = 'Дата рождения'}) =>
      TextField(
        controller: c,
        enabled: !store.sending,
        keyboardType: TextInputType.number,
        inputFormatters: [DateMaskFormatter()],
        style: const TextStyle(fontSize: 22, letterSpacing: 2),
        decoration: InputDecoration(labelText: label, hintText: 'ДД.ММ.ГГГГ'),
      );

  /// ГГГГ-ММ-ДД или null, если дата неверная.
  String? birthDate(TextEditingController c) {
    final m = RegExp(r'^(\d{2})\.(\d{2})\.(\d{4})$').firstMatch(c.text);
    if (m == null) return null;
    final d = int.parse(m[1]!), mo = int.parse(m[2]!), y = int.parse(m[3]!);
    if (y < 1920) return null;
    final date = DateTime(y, mo, d);
    if (date.year != y || date.month != mo || date.day != d) return null;
    if (date.isAfter(DateTime.now().subtract(const Duration(days: 365 * 14)))) {
      return null;
    }
    return '${m[3]}-${m[2]}-${m[1]}';
  }

  /// Новый пароль: 6 цифр. Повтор не спрашиваем — есть кнопка «показать».
  bool checkNewPin(TextEditingController pin, TextEditingController pin2) {
    if (!RegExp(r'^[0-9]{6}$').hasMatch(pin.text)) {
      snack('Пароль — 6 цифр.');
      return false;
    }
    pin2.text = pin.text;
    return true;
  }

  Widget newPinFields(TextEditingController pin, TextEditingController pin2) =>
      pinField(pin, 'Придумайте пароль — 6 цифр', length: 6);

  Widget questionFields(TextEditingController answer) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      DropdownButtonFormField<String>(
        initialValue: setQuestion,
        isExpanded: true,
        decoration: const InputDecoration(labelText: 'Контрольный вопрос'),
        items: securityQuestions.entries
            .map(
              (e) => DropdownMenuItem(
                value: e.key,
                child: Text(e.value, overflow: TextOverflow.ellipsis),
              ),
            )
            .toList(),
        onChanged: store.sending
            ? null
            : (v) => setState(() => setQuestion = v ?? setQuestion),
      ),
      const SizedBox(height: 12),
      TextField(
        controller: answer,
        enabled: !store.sending,
        maxLength: 100,
        decoration: const InputDecoration(labelText: 'Ваш ответ'),
      ),
    ],
  );

  Widget account() {
    if (store.walletSignedIn && store.wallet != null) return cabinet();
    if (store.tablet == null) {
      return centered([
        const Icon(Icons.tablet_android, size: 64, color: ink),
        const SizedBox(height: 12),
        text('Планшет не привязан к учреждению', size: 26, bold: true),
        text(
          'Личный кабинет работает только на планшете учреждения. '
          'Попросите сотрудника магазина настроить этот планшет.',
        ),
        const SizedBox(height: 20),
        OutlinedButton.icon(
          onPressed: () => setState(() => page = 6),
          icon: const Icon(Icons.settings),
          label: const Text('Настройка планшета (для сотрудника)'),
        ),
      ]);
    }
    return switch (accMode) {
      'register' => registerForm(),
      'recover' => recoveryForm(),
      _ => loginForm(),
    };
  }

  void switchAccount(String mode) {
    store.error = null;
    for (final c in [accSecret, accPin, accPin2, recProof, recPin, recPin2]) {
      c.clear();
    }
    setState(() {
      showPin = recShowProof = false;
      accMode = mode;
    });
  }

  Widget nameField() => TextField(
    controller: accName,
    enabled: !store.sending,
    maxLength: 255,
    textCapitalization: TextCapitalization.words,
    decoration: const InputDecoration(labelText: 'Фамилия Имя Отчество'),
  );

  /// ФИО (минимум фамилия и имя) и дата рождения; null — если что-то не так.
  String? checkNameAndDate() {
    if (!RegExp(r'^\S+\s+\S+').hasMatch(accName.text.trim())) {
      snack('Введите фамилию и имя полностью.');
      return null;
    }
    final date = birthDate(accDay);
    if (date == null) snack('Проверьте дату рождения: ДД.ММ.ГГГГ.');
    return date;
  }

  Widget loginForm() => centered([
    text('Вход', size: 30, bold: true),
    text(store.tablet!.institutionName, size: 15),
    const SizedBox(height: 20),
    nameField(),
    if (loginNeedsDate) dateField(accDay),
    const SizedBox(height: 12),
    pinField(accSecret, 'Пароль', length: 6),
    errorText(),
    const SizedBox(height: 8),
    FilledButton(
      onPressed: store.sending ? null : submitLogin,
      child: Text(store.sending ? 'Проверяем…' : 'Войти'),
    ),
    TextButton(
      onPressed: store.sending ? null : () => switchAccount('recover'),
      child: const Text('Забыли пароль?'),
    ),
    const SizedBox(height: 12),
    OutlinedButton(
      onPressed: store.sending ? null : () => switchAccount('register'),
      child: const Text('Регистрация'),
    ),
  ]);

  /// Вход по ФИО и паролю. Дату рождения спрашиваем, только если сервер
  /// попросил (есть тёзка или сервер старой версии).
  Future<void> submitLogin() async {
    if (!RegExp(r'^\S+\s+\S+').hasMatch(accName.text.trim())) {
      snack('Введите фамилию и имя полностью.');
      return;
    }
    var date = '';
    if (loginNeedsDate) {
      final d = checkNameAndDate();
      if (d == null) return;
      date = d;
    }
    if (accSecret.text.trim().isEmpty) {
      snack('Введите пароль.');
      return;
    }
    final ok = await store.signIn(accName.text, date, accSecret.text);
    if (!mounted) return;
    if (!ok &&
        !loginNeedsDate &&
        const [409, 422].contains(store.errorStatus)) {
      setState(() {
        loginNeedsDate = true;
        store.error = 'Укажите ещё дату рождения.';
      });
      return;
    }
    accSecret.clear();
    if (ok) store.loadHistory();
  }

  /// Забыли пароль: ФИО + дата рождения (+ ответ на вопрос, если выбирали его) + новый пароль.
  Widget recoveryForm() => centered([
    text('Новый пароль', size: 30, bold: true),
    text(store.tablet!.institutionName, size: 15),
    const SizedBox(height: 20),
    nameField(),
    dateField(accDay),
    if (recShowProof)
      TextField(
        controller: recProof,
        enabled: !store.sending,
        maxLength: 100,
        decoration: const InputDecoration(
          labelText: 'Ответ на контрольный вопрос',
          counterText: '',
        ),
      )
    else
      Align(
        alignment: Alignment.centerLeft,
        child: TextButton(
          onPressed: () => setState(() => recShowProof = true),
          child: const Text('У меня контрольный вопрос'),
        ),
      ),
    const SizedBox(height: 8),
    newPinFields(recPin, recPin2),
    errorText(),
    const SizedBox(height: 8),
    FilledButton(
      onPressed: store.sending
          ? null
          : () async {
              final date = checkNameAndDate();
              if (date == null || !checkNewPin(recPin, recPin2)) return;
              final ok = await store.recover(
                accName.text,
                date,
                recProof.text,
                recPin.text,
                recPin2.text,
              );
              recPin.clear();
              recPin2.clear();
              if (mounted && ok) {
                accMode = 'login';
                store.loadHistory();
              }
            },
      child: Text(store.sending ? 'Проверяем…' : 'Сохранить и войти'),
    ),
    TextButton(
      onPressed: store.sending ? null : () => switchAccount('login'),
      child: const Text('Назад'),
    ),
  ]);

  /// Регистрация: ФИО, условия, отряд, дата рождения, пароль, согласие.
  Widget registerForm() => centered([
    text('Регистрация', size: 30, bold: true),
    text(store.tablet!.institutionName, size: 15),
    const SizedBox(height: 20),
    nameField(),
    DropdownButtonFormField<String>(
      initialValue: accCondition,
      isExpanded: true,
      decoration: const InputDecoration(labelText: 'Условия содержания'),
      items: prisonerConditions.entries
          .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
          .toList(),
      onChanged: store.sending ? null : (v) => setState(() => accCondition = v),
    ),
    const SizedBox(height: 12),
    TextField(
      controller: accSquad,
      enabled: !store.sending,
      maxLength: 50,
      decoration: const InputDecoration(
        labelText: 'Номер отряда',
        counterText: '',
      ),
    ),
    const SizedBox(height: 12),
    dateField(accDay),
    const SizedBox(height: 12),
    newPinFields(accPin, accPin2),
    const SizedBox(height: 8),
    Row(
      children: [
        Checkbox(
          value: regConsent,
          onChanged: store.sending
              ? null
              : (v) => setState(() => regConsent = v ?? false),
        ),
        Expanded(
          child: GestureDetector(
            onTap: store.sending
                ? null
                : () => setState(() => regConsent = !regConsent),
            child: const Text(
              'Согласен на обработку моих данных',
              style: TextStyle(fontSize: 16),
            ),
          ),
        ),
        TextButton(onPressed: showConsent, child: const Text('Подробнее')),
      ],
    ),
    errorText(),
    const SizedBox(height: 12),
    FilledButton(
      onPressed: store.sending
          ? null
          : () async {
              final date = checkNameAndDate();
              if (date == null) return;
              if (accCondition == null) {
                snack('Выберите условия содержания.');
                return;
              }
              if (accSquad.text.trim().isEmpty) {
                snack('Укажите номер отряда.');
                return;
              }
              if (!checkNewPin(accPin, accPin2)) return;
              if (!regConsent) {
                snack('Отметьте согласие.');
                return;
              }
              final ok = await store.register({
                'full_name': accName.text.trim(),
                'condition': accCondition,
                'squad': accSquad.text.trim(),
                'birth_date': date,
                'pin': accPin.text,
                'pin_confirmation': accPin2.text,
                'consent': true,
              });
              accPin.clear();
              accPin2.clear();
              if (mounted && ok) {
                accMode = 'login';
                store.loadHistory();
              }
            },
      child: Text(store.sending ? 'Сохраняем…' : 'Зарегистрироваться'),
    ),
    TextButton(
      onPressed: store.sending ? null : () => switchAccount('login'),
      child: const Text('Уже есть кабинет — войти'),
    ),
  ]);

  void showConsent() => showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('Согласие на обработку данных'),
      content: const Text(
        'Магазин Dosmart хранит ваши ФИО, дату рождения, отряд, учреждение, '
        'счёт и покупки, чтобы вести личный кабинет. Сотрудник магазина '
        'проверит данные до первой покупки. Отозвать согласие можно через '
        'сотрудника магазина.',
        style: TextStyle(fontSize: 16),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Понятно'),
        ),
      ],
    ),
  );

  Widget metric(String label, String value, {String? hint, Color? tint}) =>
      Expanded(
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: tint ?? Colors.white,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              text(label, size: 14),
              const SizedBox(height: 6),
              text(value, size: 24, bold: true),
              if (hint != null) ...[
                const SizedBox(height: 4),
                text(hint, size: 13),
              ],
            ],
          ),
        ),
      );

  Widget action(
    IconData icon,
    String label,
    VoidCallback? onTap, {
    bool primary = false,
  }) => Expanded(
    child: SizedBox(
      height: 96,
      child: primary
          ? FilledButton(
              onPressed: onTap,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(icon, size: 30),
                  const SizedBox(height: 6),
                  Text(label),
                ],
              ),
            )
          : OutlinedButton(
              style: OutlinedButton.styleFrom(
                foregroundColor: ink,
                backgroundColor: Colors.white,
                side: const BorderSide(color: ink, width: 1.2),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                textStyle: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w600,
                ),
              ),
              onPressed: onTap,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(icon, size: 30),
                  const SizedBox(height: 6),
                  Text(label),
                ],
              ),
            ),
    ),
  );

  void signOut() {
    store.clearWallet();
    walletMode = false;
    setState(() => page = 0);
  }

  Widget cabinet() {
    final profile = store.wallet!;
    final limit = profile.monthlyLimitMinor;
    final remaining = profile.monthlyRemainingMinor;
    final available = (limit ?? 0) + profile.limitCarriedMinor;
    return RefreshIndicator(
      onRefresh: store.loadHistory,
      child: centered([
        text(profile.fullName, size: 28, bold: true),
        text(
          [
            profile.institution,
            if ((profile.squad ?? '').isNotEmpty) 'отряд ${profile.squad}',
            if (profile.condition.isNotEmpty)
              '${profile.condition} условия'
                  '${profile.conditionNormMrp == null ? '' : ' (${profile.conditionNormMrp} МРП)'}',
          ].where((s) => s.isNotEmpty).join(' · '),
          size: 15,
        ),
        if (!profile.reviewed) ...[
          const SizedBox(height: 12),
          panel(
            text(
              'Ждёт проверки сотрудником. Покупки со счёта откроются после '
              'проверки, заказ через родственника — уже сейчас.',
              size: 15,
            ),
          ),
        ],
        const SizedBox(height: 20),
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              metric(
                'Счёт',
                money(profile.balanceMinor),
                hint: 'Можно потратить в магазине',
                tint: softGreen,
              ),
              const SizedBox(width: 12),
              metric(
                'Лимит на этот месяц',
                limit == null ? 'Без лимита' : money(remaining ?? 0),
                hint: limit == null
                    ? 'Потрачено на товары: ${money(profile.monthlySpentMinor)}'
                    : [
                        'Осталось из ${money(available)}',
                        if (profile.limitCarriedMinor > 0)
                          'вкл. перенос ${money(profile.limitCarriedMinor)}',
                        'Доставка в лимит не входит',
                      ].join('\n'),
              ),
            ],
          ),
        ),
        if (limit != null && available > 0) ...[
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              minHeight: 10,
              value: (profile.monthlySpentMinor / available).clamp(0.0, 1.0),
              backgroundColor: Colors.white,
              color: ink,
            ),
          ),
        ],
        const SizedBox(height: 20),
        Row(
          children: [
            action(
              Icons.account_balance_wallet_outlined,
              'Купить со счёта',
              () {
                setState(() {
                  walletMode = true;
                  page = store.cart.isEmpty ? 1 : 2;
                });
              },
              primary: true,
            ),
            const SizedBox(width: 12),
            action(Icons.family_restroom, 'Заказать через родственника', () {
              // Заказ оплатит родственник: ФИО и отряд подставляем из кабинета.
              if (name.text.trim().isEmpty) name.text = profile.fullName;
              if (squad.text.trim().isEmpty) squad.text = profile.squad ?? '';
              setState(() {
                walletMode = false;
                consent = false;
                page = store.cart.isEmpty ? 1 : 2;
              });
            }, primary: true),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            action(Icons.settings_outlined, 'Настройки', () {
              store.error = null;
              store.notice = null;
              setState(() {
                setRecovery = profile.recoveryMethod ?? 'birth_date';
                page = 5;
              });
            }),
            const SizedBox(width: 12),
            action(Icons.logout, 'Выйти', signOut),
          ],
        ),
        if (profile.parcelsAvailable case final int parcels) ...[
          const SizedBox(height: 12),
          text('Доступно передач от родственников: $parcels', size: 15),
        ],
        const SizedBox(height: 24),
        Row(
          children: [
            Expanded(child: text('История операций', size: 20, bold: true)),
            if (store.historyLoading)
              const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            else
              IconButton(
                tooltip: 'Обновить',
                onPressed: store.loadHistory,
                icon: const Icon(Icons.refresh, color: ink),
              ),
          ],
        ),
        const SizedBox(height: 8),
        if (store.walletHistory.isEmpty && !store.historyLoading)
          panel(text('Операций пока нет.'))
        else
          panel(
            Column(
              children: [
                for (final (i, op) in store.walletHistory.indexed) ...[
                  if (i > 0) const Divider(height: 20),
                  Row(
                    children: [
                      Icon(
                        op.amountMinor >= 0
                            ? Icons.south_west
                            : Icons.north_east,
                        color: op.amountMinor >= 0 ? brandGreen : Colors.red,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            text(op.title, bold: true),
                            text(
                              op.status == null
                                  ? op.at
                                  : '${op.at} · ${op.status}',
                              size: 13,
                            ),
                          ],
                        ),
                      ),
                      Text(
                        '${op.amountMinor >= 0 ? '+' : '−'}${money(op.amountMinor.abs())}',
                        style: TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                          color: op.amountMinor >= 0 ? brandGreen : Colors.red,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
      ]),
    );
  }

  Widget settingsPage() {
    final profile = store.wallet;
    if (!store.walletSignedIn || profile == null) return account();
    return centered([
      text('Настройки', size: 30, bold: true),
      text(profile.fullName, size: 15),
      if (store.notice != null) ...[
        const SizedBox(height: 12),
        panel(text(store.notice!, bold: true)),
      ],
      errorText(),
      const SizedBox(height: 20),
      panel(
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            text('Отряд', size: 20, bold: true),
            const SizedBox(height: 6),
            text(
              'Сейчас: ${(profile.squad ?? '').isEmpty ? 'не указан' : profile.squad}. '
              'Если вас перевели — укажите новый отряд, чтобы заказ донесли правильно.',
              size: 15,
            ),
            TextField(
              controller: squadEdit,
              enabled: !store.sending,
              maxLength: 50,
              decoration: const InputDecoration(
                labelText: 'Новый номер отряда',
              ),
            ),
            FilledButton(
              onPressed: store.sending
                  ? null
                  : () async {
                      if (squadEdit.text.trim().isEmpty) {
                        snack('Укажите номер отряда.');
                        return;
                      }
                      if (await store.setSquad(squadEdit.text)) {
                        squadEdit.clear();
                      }
                    },
              child: const Text('Сохранить отряд'),
            ),
          ],
        ),
      ),
      const SizedBox(height: 20),
      panel(
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            text('Сменить пароль', size: 20, bold: true),
            const SizedBox(height: 12),
            pinField(curSecret, 'Текущий пароль'),
            newPinFields(newPin, newPin2),
            const SizedBox(height: 8),
            FilledButton(
              onPressed: store.sending
                  ? null
                  : () async {
                      if (curSecret.text.trim().isEmpty) {
                        snack('Введите текущий пароль.');
                        return;
                      }
                      if (!checkNewPin(newPin, newPin2)) return;
                      final ok = await store.changeCredentials(curSecret.text, {
                        'pin': newPin.text,
                        'pin_confirmation': newPin2.text,
                      });
                      curSecret.clear();
                      if (ok) {
                        newPin.clear();
                        newPin2.clear();
                      }
                    },
              child: const Text('Сохранить пароль'),
            ),
          ],
        ),
      ),
      const SizedBox(height: 20),
      panel(
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            text('Если забудете пароль', size: 20, bold: true),
            const SizedBox(height: 6),
            text(
              profile.recoveryMethod == 'question'
                  ? 'Сейчас: восстановление по контрольному вопросу «${profile.recoveryQuestion ?? ''}».'
                  : profile.recoveryMethod == 'birth_date'
                  ? 'Сейчас: восстановление по дате рождения.'
                  : 'Сейчас восстановить пароль можно только через сотрудника.',
              size: 15,
            ),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(
                  value: 'birth_date',
                  icon: Icon(Icons.cake_outlined),
                  label: Text('Дата рождения'),
                ),
                ButtonSegment(
                  value: 'question',
                  icon: Icon(Icons.help_outline),
                  label: Text('Контрольный вопрос'),
                ),
              ],
              selected: {setRecovery},
              onSelectionChanged: store.sending
                  ? null
                  : (v) => setState(() => setRecovery = v.first),
            ),
            const SizedBox(height: 16),
            if (setRecovery == 'question')
              questionFields(newAnswer)
            else if (!profile.birthDateSet)
              dateField(setDay),
            pinField(setCurrent, 'Текущий пароль'),
            const SizedBox(height: 8),
            FilledButton(
              onPressed: store.sending
                  ? null
                  : () async {
                      if (setCurrent.text.trim().isEmpty) {
                        snack('Введите текущий пароль.');
                        return;
                      }
                      final body = <String, dynamic>{'method': setRecovery};
                      if (setRecovery == 'question') {
                        if (newAnswer.text.trim().length < 2) {
                          snack('Введите ответ на вопрос.');
                          return;
                        }
                        body['question'] = setQuestion;
                        body['answer'] = newAnswer.text.trim();
                      } else if (!profile.birthDateSet) {
                        final date = birthDate(setDay);
                        if (date == null) {
                          snack('Проверьте дату рождения.');
                          return;
                        }
                        body['birth_date'] = date;
                      }
                      final ok = await store.setRecovery(setCurrent.text, body);
                      setCurrent.clear();
                      if (ok) newAnswer.clear();
                    },
              child: const Text('Сохранить способ восстановления'),
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      TextButton.icon(
        onPressed: () => setState(() => page = 4),
        icon: const Icon(Icons.arrow_back),
        label: const Text('Назад в кабинет'),
      ),
    ]);
  }

  Widget tabletSetup() => centered([
    const Icon(Icons.tablet_android, size: 64, color: ink),
    const SizedBox(height: 12),
    text('Настройка планшета', size: 30, bold: true),
    text(
      store.tablet == null
          ? 'Только для сотрудника. Введите код учреждения из админки: '
                'Учёт лимитов → учреждение → «Планшеты учреждения».'
          : 'Сейчас планшет привязан к учреждению «${store.tablet!.institutionName}». '
                'Чтобы сменить, введите код другого учреждения.',
    ),
    const SizedBox(height: 20),
    TextField(
      controller: tabletCode,
      enabled: !store.sending,
      keyboardType: TextInputType.number,
      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
      maxLength: 8,
      style: const TextStyle(fontSize: 26, letterSpacing: 6),
      decoration: const InputDecoration(labelText: 'Код учреждения'),
    ),
    errorText(),
    const SizedBox(height: 8),
    FilledButton(
      onPressed: store.sending
          ? null
          : () async {
              final ok = await store.setupTablet(tabletCode.text);
              if (!mounted || !ok) return;
              tabletCode.clear();
              snack('Планшет привязан: ${store.tablet!.institutionName}');
              setState(() => page = 0);
            },
      child: Text(store.sending ? 'Проверяем…' : 'Привязать планшет'),
    ),
  ]);

  Widget done() {
    final receipt = store.walletReceipt;
    if (receipt != null) {
      return centered([
        const Icon(Icons.check_circle_outline, size: 80, color: ink),
        text('Заказ оплачен со счёта', size: 30, bold: true),
        text('Номер: ${receipt.orderNumber}'),
        text('Списано с доставкой: ${money(receipt.totalMinor)}'),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: finish,
          child: const Text('Готово — выйти в меню'),
        ),
      ]);
    }
    return centered([
      const Icon(Icons.check_circle_outline, size: 80, color: ink),
      const SizedBox(height: 24),
      text('Заявка отправлена в магазин', size: 30, bold: true),
      const SizedBox(height: 16),
      text('Номер: ${store.sent?.orderNumber ?? ''}', size: 23, bold: true),
      text('Доставка — 3 000 ₸', size: 20, bold: true),
      const SizedBox(height: 16),
      text(
        'Сотрудники Dosmart свяжутся с родственником по указанному WhatsApp. Они согласуют товары, доставку и оплату.',
      ),
      const SizedBox(height: 24),
      panel(
        text(
          'Свяжитесь с родственником через Soilephone. Сообщите номер заявки и попросите дождаться обращения магазина. Данные формы уже очищены.',
        ),
      ),
      const SizedBox(height: 24),
      FilledButton(
        onPressed: finish,
        child: const Text('Готово — выйти в меню'),
      ),
    ]);
  }

  @override
  Widget build(BuildContext context) => Listener(
    onPointerDown: (_) => touch(),
    child: PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && !store.sending) {
          if (page == 2) {
            setState(() => page = 1);
          } else if (page == 5) {
            setState(() => page = 4);
          } else if (page == 6 || (page == 4 && !store.walletSignedIn)) {
            if (page == 4) store.clearWallet();
            setState(() => page = 0);
          } else {
            finish();
          }
        }
      },
      child: Scaffold(
        appBar: AppBar(
          leading: [2, 4, 5, 6].contains(page)
              ? IconButton(
                  onPressed: store.sending
                      ? null
                      : () => setState(() {
                          if (page == 4 && !store.walletSignedIn) {
                            store.clearWallet();
                          }
                          page = switch (page) {
                            2 => 1,
                            5 => 4,
                            4 => store.walletSignedIn ? 1 : 0,
                            _ => 0,
                          };
                        }),
                  icon: const Icon(Icons.arrow_back),
                )
              : null,
          title: const Text(
            'Dosmart',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          actions: [
            if (page == 1 || page == 2)
              IconButton(
                tooltip: 'Личный кабинет',
                onPressed: store.sending ? null : openAccount,
                icon: const Icon(Icons.account_balance_wallet_outlined),
              ),
            if (page == 1)
              TextButton(
                onPressed: () => setState(() => page = 2),
                child: ListenableBuilder(
                  listenable: store,
                  builder: (_, _) => Text('Корзина (${store.count})'),
                ),
              ),
            IconButton(
              tooltip: 'Завершить сеанс',
              onPressed: store.sending ? null : finish,
              icon: const Icon(Icons.logout),
            ),
          ],
        ),
        body: SafeArea(
          child: switch (page) {
            0 => intro(),
            1 => catalog(),
            2 => basket(),
            4 => account(),
            5 => settingsPage(),
            6 => tabletSetup(),
            _ => done(),
          },
        ),
      ),
    ),
  );
}

/// Маска даты: оставляет до 8 цифр и ставит точки — ДД.ММ.ГГГГ.
class DateMaskFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    var digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    // Стёрли точку — стираем и цифру перед ней.
    if (newValue.text.length < oldValue.text.length &&
        oldValue.text.endsWith('.') &&
        digits == oldValue.text.replaceAll(RegExp(r'\D'), '')) {
      digits = digits.substring(0, digits.length - 1);
    }
    if (digits.length > 8) digits = digits.substring(0, 8);
    final out = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      out.write(digits[i]);
      if ((i == 1 || i == 3) &&
          (i < digits.length - 1 ||
              newValue.text.length > oldValue.text.length)) {
        out.write('.');
      }
    }
    final text = out.toString();
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
