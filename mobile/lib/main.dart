import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart';
import 'api.dart';
import 'models.dart';
import 'store.dart';

const ink = Color(0xFF2E3260);
const yellow = Color(0xFFFFFF15);
String money(int value) =>
    '${NumberFormat('#,##0.##', 'ru').format(value / 100)} ₸';
void main() => runApp(const DoSmartApp());

class DoSmartApp extends StatelessWidget {
  const DoSmartApp({super.key, this.store});
  final Store? store;
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'DoSmart',
    debugShowCheckedModeBanner: false,
    locale: const Locale('ru'),
    supportedLocales: const [Locale('ru')],
    localizationsDelegates: GlobalMaterialLocalizations.delegates,
    theme: ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: ink),
      scaffoldBackgroundColor: const Color(0xFFF5F6FA),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: ink,
        centerTitle: false,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: yellow,
          foregroundColor: ink,
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
      search = TextEditingController();
  final form = GlobalKey<FormState>();
  int page = 0; // Instructions, catalog, basket, confirmation.
  int? institution, category;
  bool consent = false;
  Timer? idle;
  DateTime lastActivity = DateTime.now();
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    store = widget.store ?? Store(ShopApi());
    store.addListener(refresh);
    store.load();
    touch();
  }

  void refresh() {
    if (mounted) setState(() {});
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
    name.clear();
    phone.clear();
    search.clear();
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
    WidgetsBinding.instance.removeObserver(this);
    store.removeListener(refresh);
    if (widget.store == null) store.dispose();
    name.dispose();
    phone.dispose();
    search.dispose();
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

  Future<void> send() async {
    if (!form.currentState!.validate() || !consent || institution == null) {
      return;
    }
    final ok = await store.submit(name.text, institution!, phone.text);
    if (mounted && ok) {
      name.clear();
      phone.clear();
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
          backgroundColor: yellow,
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
    SizedBox(
      height: 100,
      child: Image.asset('assets/dosmart.png', fit: BoxFit.contain),
    ),
    text('Нужные вещи для вас', size: 32, bold: true),
    const SizedBox(height: 12),
    text(
      'Соберите корзину. Сотрудники DoSmart свяжутся с вашим родственником и помогут оформить заказ.',
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
    const SizedBox(height: 24),
    FilledButton(
      onPressed: () => setState(() => page = 1),
      child: const Text('Понятно, перейти в магазин'),
    ),
  ]);
  Widget quantity(ShopProduct p) {
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

  Widget product(ShopProduct p) => Card(
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
                ? const Icon(Icons.shopping_bag_outlined, size: 60, color: ink)
                : Image.network(
                    p.imageUrl!,
                    fit: BoxFit.contain,
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
    final visible = store.products
        .where(
          (p) =>
              (category == null || p.categoryId == category) &&
              p.name.toLowerCase().contains(search.text.toLowerCase()),
        )
        .toList();
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
          child: TextField(
            controller: search,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              hintText: 'Найти товар',
              prefixIcon: Icon(Icons.search),
            ),
          ),
        ),
        SizedBox(
          height: 60,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: const Text('Все товары'),
                  selected: category == null,
                  onSelected: (_) => setState(() => category = null),
                ),
              ),
              ...store.categories.map(
                (c) => Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(c.name),
                    selected: category == c.id,
                    onSelected: (_) => setState(() => category = c.id),
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: visible.isEmpty
              ? Center(child: text('Товары не найдены'))
              : LayoutBuilder(
                  builder: (c, size) {
                    final columns = (size.maxWidth / 220).floor().clamp(1, 5);
                    return GridView.builder(
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
              label: Text(
                'Перейти в корзину (${store.count}) · ${money(store.total)}',
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
      text(
        'Доставку и окончательную сумму сотрудник согласует с родственником.',
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
              maxLength: 30,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'WhatsApp родственника',
                hintText: '+7 701 123 45 67',
                helperText: 'Проверьте номер — на него напишет магазин',
              ),
              validator: (v) =>
                  RegExp(
                    r'^\+?[1-9][0-9]{9,14}$',
                  ).hasMatch((v ?? '').replaceAll(RegExp(r'[\s()\-]'), ''))
                  ? null
                  : 'Введите номер с кодом страны',
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
    ]);
  }

  Widget done() => centered([
    const Icon(Icons.check_circle_outline, size: 80, color: ink),
    const SizedBox(height: 24),
    text('Заявка отправлена в магазин', size: 30, bold: true),
    const SizedBox(height: 16),
    text('Номер: ${store.sent?.orderNumber ?? ''}', size: 23, bold: true),
    const SizedBox(height: 16),
    text(
      'Сотрудники DoSmart свяжутся с родственником по указанному WhatsApp. Они согласуют товары, доставку и оплату.',
    ),
    const SizedBox(height: 24),
    panel(
      text(
        'Всё готово. Сообщение в чат Сойлефона отправлять не нужно. Данные формы уже очищены.',
      ),
    ),
    const SizedBox(height: 24),
    FilledButton(onPressed: finish, child: const Text('Готово — выйти в меню')),
  ]);
  @override
  Widget build(BuildContext context) => Listener(
    onPointerDown: (_) => touch(),
    child: PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && !store.sending) {
          if (page == 2) {
            setState(() => page = 1);
          } else {
            finish();
          }
        }
      },
      child: Scaffold(
        appBar: AppBar(
          leading: page == 2
              ? IconButton(
                  onPressed: store.sending
                      ? null
                      : () => setState(() => page = 1),
                  icon: const Icon(Icons.arrow_back),
                )
              : null,
          title: const Text(
            'DoSmart',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          actions: [
            if (page == 1)
              TextButton(
                onPressed: () => setState(() => page = 2),
                child: Text('Корзина (${store.count})'),
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
            _ => done(),
          },
        ),
      ),
    ),
  );
}
