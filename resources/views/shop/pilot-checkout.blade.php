@extends('shop.layout')
@section('title', 'Оформление заявки — Dosmart')
@section('body-class', 'checkout-page')
@section('content')
<div class="checkout-wrap">
<a class="checkout-back" href="{{ route('shop.cart') }}">← К корзине</a>
<div class="checkout-heading"><span class="checkout-kicker">ШАГ 2 ИЗ 2</span><h1>Кому передать заказ?</h1><p>Укажите получателя и WhatsApp для связи. Вход и регистрация не нужны.</p></div>
@if($errors->any())<div class="checkout-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form id="checkout-form" class="checkout-form" method="post" action="{{ route('pilot.checkout',$draft->code) }}">@csrf
<div class="checkout-grid">
<section class="checkout-panel"><h2>Данные получателя</h2>
<div class="form-group"><label class="form-label" for="prisoner_name">ФИО получателя полностью</label><input class="form-control" id="prisoner_name" name="prisoner_name" maxlength="255" value="{{ old('prisoner_name',$draft->prisoner_name) }}" required></div>
<div class="form-group"><label class="form-label" for="institution_id">Учреждение</label><select class="form-control" id="institution_id" name="institution_id" required><option value="">Выберите учреждение</option>@foreach($institutions as $institution)<option value="{{ $institution->id }}" data-parcel-limited="{{ (int) ($institution->requiresParcelCode()) }}" @selected(old('institution_id',$draft->institution_id) == $institution->id)>{{ $institution->name }} · {{ $institution->city }}</option>@endforeach</select></div>
<div class="form-group" id="recipient-limits-group"><label class="form-label" for="recipient_birth_date">Дата рождения получателя</label><input class="form-control" id="recipient_birth_date" name="recipient_birth_date" type="date" min="1920-01-01" max="{{ now('Asia/Almaty')->subYears(14)->toDateString() }}" value="{{ old('recipient_birth_date') }}" autocomplete="off"><p class="checkout-note">Если получатель зарегистрирован в личном кабинете на планшете, укажите его дату рождения — покажем его лимиты, чтобы вы не заказали лишнего. Если кабинета нет — оставьте поле пустым, заказ оформится как обычно. Дата рождения в заказ не сохраняется.</p>
<button type="button" class="btn btn-ghost" id="check-recipient-limits">Показать лимиты получателя</button>
<p class="checkout-note" id="recipient-limits-result" role="status"></p></div>
@if($institutions->contains(fn ($institution) => $institution->requiresParcelCode()))
<div class="form-group" id="parcel-code-group"><label class="form-label" for="parcel_code">Код получателя</label><input class="form-control" id="parcel_code" name="parcel_code" maxlength="24" minlength="24" autocomplete="off"><p class="checkout-note">Если получатель не зарегистрирован в кабинете, вместо даты рождения можно указать код получателя — его даёт сотрудник Dosmart.</p>
<button type="button" class="btn btn-ghost" id="check-parcel-limit">Показать остаток лимита</button>
<p class="checkout-note" id="parcel-limit-result" role="status"></p></div>
@endif
<div class="form-group"><label class="form-label" for="squad_number">Отряд</label><input class="form-control" id="squad_number" name="squad_number" maxlength="50" value="{{ old('squad_number') }}" required></div>
<div class="form-group"><label class="form-label" for="relative_name">Ваши ФИО (отправитель)</label><input class="form-control" id="relative_name" name="relative_name" maxlength="255" autocomplete="name" value="{{ old('relative_name', auth('customer')->user()?->name) }}" required><p class="checkout-note">Будут указаны в чеке как отправитель.</p></div>
<div class="form-group"><label class="form-label" for="contact_phone">Ваш WhatsApp для связи</label><input class="form-control" id="contact_phone" name="contact_phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="+7 705 621 8748" pattern="[+]7 [0-9]{3} [0-9]{3} [0-9]{4}" title="Введите номер в формате +7 705 621 8748" value="{{ old('contact_phone', auth('customer')->user()?->phone) }}" required><p class="checkout-note">На этот номер сотрудник напишет по заказу.</p></div>
<input type="hidden" name="delivery_type" value="standard">
<div class="checkout-section-note"><strong>Доставка — {{ number_format((float) config('pilot.delivery_fee', 3000),0,',',' ') }} ₸</strong><br>Дата и время передачи согласовываются с сотрудником.</div>
</section>
<aside class="checkout-panel checkout-summary"><h2>Ваш заказ</h2>
<div class="checkout-quote-list">
@foreach($quote['items'] as $item)<div class="checkout-quote-item"><span>{{ $item['product_name'] }}<small>{{ $item['quantity'] }} × {{ number_format($item['price'],2,',',' ') }} ₸</small></span><strong>{{ number_format($item['total'],2,',',' ') }} ₸</strong></div>@endforeach
</div>
<div class="checkout-money"><span>Товары</span><strong>{{ number_format($quote['subtotal'],2,',',' ') }} ₸</strong></div>
<div class="checkout-money"><span>Доставка</span><strong>{{ number_format((float) config('pilot.delivery_fee', 3000),0,',',' ') }} ₸</strong></div>
<div class="checkout-grand"><span>С доставкой</span><strong id="pilot-total">{{ number_format($quote['subtotal'] + (float) config('pilot.delivery_fee',3000),2,',',' ') }} ₸</strong></div>
@include('shop.partials.consumer-links')
<label class="checkout-consent"><input type="checkbox" name="consent" value="1" required><span>Согласен(на) на обработку данных для оформления и доставки заказа. <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Подробнее</a></span></label>
<input type="hidden" name="expected_total" id="expected_total" value="{{ number_format($quote['subtotal'] + (float) config('pilot.delivery_fee',3000),2,'.','') }}">
<button class="btn btn-primary btn-block btn-lg" id="confirm-order">Подтвердить и перейти к оплате</button>
<p class="checkout-note">После оплаты отправьте квитанцию в WhatsApp сотруднику и укажите номер заявки и ФИО получателя. Поступление денег проверяется вручную.</p>
</aside></div></form></div>
@endsection
@section('scripts')
<script>
(() => {
 const button = document.getElementById('check-recipient-limits');
 if (!button) return;
 const result = document.getElementById('recipient-limits-result');
 const form = document.getElementById('checkout-form');
 const subtotal = {{ (int) round($quote['subtotal'] * 100) }};
 const tenge = (minor) => new Intl.NumberFormat('ru-RU').format(Math.round(minor / 100)) + ' ₸';
 const auto = () => {
  if (form.elements.prisoner_name.value.trim().length >= 3 && form.elements.institution_id.value && document.getElementById('recipient_birth_date').value) button.click();
 };
 document.getElementById('recipient_birth_date').addEventListener('change', auto);
 form.elements.institution_id.addEventListener('change', auto);
 form.elements.prisoner_name.addEventListener('change', auto);
 button.addEventListener('click', async () => {
  const name = form.elements.prisoner_name.value.trim();
  const institution = form.elements.institution_id.value;
  const birth = document.getElementById('recipient_birth_date').value;
  if (name.length < 3 || !institution || !birth) { result.textContent = 'Укажите ФИО получателя, учреждение и дату рождения.'; return; }
  button.disabled = true; result.textContent = 'Проверяем…';
  try {
   const response = await fetch(@json(route('pilot.recipient-limits', $draft->code, false)), {
    method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
    body: JSON.stringify({institution_id: institution, prisoner_name: name, birth_date: birth}),
   });
   if (response.status === 404) { result.textContent = 'Личный кабинет получателя не найден. Если он не регистрировался на планшете — оформляйте заказ как обычно. Если регистрировался — проверьте ФИО полностью и дату рождения.'; return; }
   if (response.status === 429) { result.textContent = 'Слишком много попыток. Повторите через час.'; return; }
   if (!response.ok) { result.textContent = 'Не удалось проверить. Повторите позже.'; return; }
   const data = await response.json();
   const lines = [];
   const caps = [];
   if (data.monthly_remaining_minor !== null) { lines.push('Месячный лимит покупок: осталось ' + tenge(data.monthly_remaining_minor) + ' из ' + tenge(data.monthly_limit_minor) + '.'); caps.push(data.monthly_remaining_minor); }
   else lines.push('Месячный лимит покупок: без ограничения.');
   if (data.parcels_available !== null) { lines.push('Передач доступно в этом году: ' + data.parcels_available + '.'); if (data.parcels_available === 0) lines.push('Передачи на этот год закончились — заказ оформить нельзя.'); }
   if (data.parcel_budget_minor !== null) { lines.push('Лимит передач в этом месяце: осталось ' + tenge(data.parcel_budget_minor) + '.'); caps.push(data.parcel_budget_minor); }
   if (caps.length && subtotal > Math.min(...caps)) lines.push('Внимание: товаров в корзине на ' + tenge(subtotal) + ' — это больше остатка лимита. Уберите лишнее, иначе учреждение может не принять передачу.');
   result.textContent = lines.join(' ');
  } catch (e) { result.textContent = 'Нет связи. Повторите позже.'; }
  finally { button.disabled = false; }
 });
})();
const institutionField = document.getElementById('institution_id');
const parcelCodeGroup = document.getElementById('parcel-code-group');
if (parcelCodeGroup) {
 const parcelCode = document.getElementById('parcel_code');
 const updateParcelCode = () => {
  const required = institutionField.selectedOptions[0]?.dataset.parcelLimited === '1';
  parcelCodeGroup.hidden = !required;
  // Код необязателен: получателя можно найти по ФИО и дате рождения. Сервер проверит одно из двух.
  parcelCode.required = false;
  parcelCode.disabled = !required;
 };
 institutionField.addEventListener('change', updateParcelCode);
 const result = document.getElementById('parcel-limit-result');
 const check = document.getElementById('check-parcel-limit');
 const form = check.form;
 let revision = 0;
 const clearLimit = () => { revision++; result.textContent = ''; check.disabled = false; };
 institutionField.addEventListener('change', clearLimit);
 parcelCode.addEventListener('input', clearLimit);
 check.addEventListener('click', async () => {
  const code = parcelCode.value.trim();
  if (!/^[a-f0-9]{24}$/i.test(code)) {
   result.textContent = 'Введите код получателя из 24 символов.';
   return;
  }
  const current = ++revision;
  check.disabled = true;
  result.textContent = 'Проверяем лимит…';
  try {
   const response = await fetch(@json(route('pilot.parcel-limit', $draft->code, false)), {
    method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
    body: JSON.stringify({institution_id: institutionField.value, parcel_code: code}),
   });
   if (current !== revision) return;
   if (response.status === 404) { result.textContent = 'Проверьте код получателя и учреждение.'; return; }
   if (!response.ok) throw new Error();
   const data = await response.json();
   if (current !== revision) return;
   if (data.budget?.remaining_minor != null) {
    const money = new Intl.NumberFormat('ru-KZ', {style: 'currency', currency: 'KZT'});
    result.textContent = 'Остаток на ' + data.budget.period + ': ' + money.format(data.budget.remaining_minor / 100)
     + '. Доставка оплачивается отдельно.'
     + (subtotal * 100 > data.budget.remaining_minor ? ' Товары в корзине превышают остаток.' : '');
   } else if (data.parcels_available != null) {
    result.textContent = 'Доступно передач: ' + data.parcels_available + '.';
   } else {
    result.textContent = 'Норма на текущий период пока не настроена.';
   }
  } catch (_) {
   if (current === revision) result.textContent = 'Не удалось проверить лимит. Попробуйте ещё раз.';
  } finally {
   if (current === revision) check.disabled = false;
  }
 });
 updateParcelCode();
}
const phoneField = document.getElementById('contact_phone');
function formatPhone(value) {
 let digits = value.replace(/\D/g, '');
 if (value.trim().startsWith('+7') || (digits.length === 11 && /^[78]/.test(digits))) digits = digits.slice(1);
 digits = digits.slice(0, 10);
 return '+7' + (digits ? ' ' + digits.slice(0,3) : '') + (digits.length > 3 ? ' ' + digits.slice(3,6) : '') + (digits.length > 6 ? ' ' + digits.slice(6) : '');
}
phoneField.addEventListener('input', () => {
 const raw = phoneField.value, cursor = phoneField.selectionStart ?? raw.length;
 const count = raw.slice(0,cursor).replace(/\D/g,'').length;
 const hadPrefix = raw.trim().startsWith('+7') || (raw.replace(/\D/g,'').length === 11 && /^[78]/.test(raw.replace(/\D/g,'')));
 phoneField.value = formatPhone(raw);
 const target = count + (hadPrefix ? 0 : 1);
 let seen = 0, position = phoneField.value.length;
 for (let i=0;i<phoneField.value.length;i++) if (/\d/.test(phoneField.value[i]) && ++seen === target) { position=i+1; break; }
 phoneField.setSelectionRange(Math.max(2,position),Math.max(2,position));
});
phoneField.addEventListener('keydown', event => {
 const pos = phoneField.selectionStart;
 if (pos !== phoneField.selectionEnd) return;
 if (event.key === 'Backspace' && pos <= 2) event.preventDefault();
 else if (event.key === 'Backspace' && phoneField.value[pos-1] === ' ' && pos > 3) phoneField.setSelectionRange(pos-2,pos);
 else if (event.key === 'Delete' && phoneField.value[pos] === ' ') phoneField.setSelectionRange(pos,pos+2);
});
phoneField.addEventListener('focus', () => { if (!phoneField.value) phoneField.value = '+7'; });
if (phoneField.value) phoneField.value = formatPhone(phoneField.value);
const subtotal = {{ (float) $quote['subtotal'] }};
function updateDelivery() {
 const fee = @json((float) config('pilot.delivery_fee', 3000));
 document.getElementById('pilot-total').textContent = (subtotal+fee).toLocaleString('ru-RU')+' ₸';
 document.getElementById('expected_total').value = (subtotal+fee).toFixed(2);
}
document.querySelectorAll('[name=delivery_type]').forEach(el=>el.addEventListener('change',updateDelivery));
updateDelivery();
document.getElementById('confirm-order').form.addEventListener('submit',()=>{document.getElementById('confirm-order').disabled=true;});
</script>
@endsection
