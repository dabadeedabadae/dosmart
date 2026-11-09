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
<div class="form-group"><label class="form-label" for="institution_id">Учреждение</label><select class="form-control" id="institution_id" name="institution_id" required><option value="">Выберите учреждение</option>@foreach($institutions as $institution)<option value="{{ $institution->id }}" @selected(old('institution_id',$draft->institution_id) == $institution->id)>{{ $institution->name }} · {{ $institution->city }}</option>@endforeach</select></div>
<div class="form-group"><label class="form-label" for="squad_number">Отряд</label><input class="form-control" id="squad_number" name="squad_number" maxlength="50" value="{{ old('squad_number') }}" required></div>
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
