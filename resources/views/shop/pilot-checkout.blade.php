@extends('shop.layout')
@section('title', 'Корзина от близкого — DOSMART')
@section('content')
<div style="max-width:740px;margin:auto">
<div class="eyebrow">КОРЗИНА {{ $draft->code }}</div><h1>Передайте заботу.</h1>
<p class="intro">Проверьте товары и укажите данные для доставки. Регистрация не обязательна. Корзина доступна до {{ $draft->expires_at->format('d.m.Y H:i') }}.</p>
@if($errors->any())<div class="form-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ route('pilot.checkout',$draft->code) }}">@csrf
<div class="form-card" style="margin-bottom:20px"><h2 style="margin-bottom:16px">Товары</h2>
@foreach($quote['items'] as $item)<div class="summary-row"><span>{{ $item['product_name'] }} × {{ $item['quantity'] }}</span><strong>{{ number_format($item['total'],2,',',' ') }} ₸</strong></div>@endforeach
</div>
<div class="form-card">
<h2 style="margin-bottom:20px">Получатель и доставка</h2>
<div class="form-group"><label class="form-label" for="prisoner_name">ФИО осуждённого</label><input class="form-control" id="prisoner_name" name="prisoner_name" maxlength="255" value="{{ old('prisoner_name',$draft->prisoner_name) }}" required></div>
<div class="form-group"><label class="form-label" for="institution_id">Учреждение</label><select class="form-control" id="institution_id" name="institution_id" required><option value="">Выберите учреждение</option>@foreach($institutions as $institution)<option value="{{ $institution->id }}" @selected(old('institution_id',$draft->institution_id) == $institution->id)>{{ $institution->name }} · {{ $institution->city }}</option>@endforeach</select></div>
<div class="form-group"><label class="form-label" for="contact_phone">Ваш WhatsApp</label><input class="form-control" id="contact_phone" name="contact_phone" type="tel" autocomplete="tel" placeholder="+7 700 123 45 67" value="{{ old('contact_phone', auth('customer')->user()?->phone) }}" required></div>
<fieldset style="border:0;margin:20px 0"><legend class="form-label">Доставка</legend>
<label style="display:block;padding:12px 0"><input type="radio" name="delivery_type" value="standard" @checked(old('delivery_type','standard') === 'standard')> На следующий день — 3 000 ₸</label>
<label style="display:block;padding:12px 0"><input type="radio" name="delivery_type" value="urgent" @checked(old('delivery_type') === 'urgent')> Срочно — 5 000 ₸</label>
<p class="form-hint">Время передачи уточнит сотрудник при подтверждении заказа.</p></fieldset>
<label style="display:flex;gap:10px;font-size:13px;line-height:1.7"><input type="checkbox" name="consent" value="1" required style="align-self:flex-start;margin-top:5px"><span>Согласен(на) на обработку введённых данных для оформления, оплаты и доставки заказа. <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Подробнее</a></span></label>
<div class="summary-total" style="margin:24px 0"><span>Итого с доставкой</span><span id="pilot-total"></span></div>
<input type="hidden" name="expected_total" id="expected_total">
<button class="btn btn-primary btn-block btn-lg" id="confirm-order">Подтвердить и перейти к оплате</button>
<p class="form-hint" style="margin-top:12px">Оплату подтверждает сотрудник после сверки в Kaspi Pay.</p>
</div></form></div>
@endsection
@section('scripts')
<script>
const subtotal = {{ (float) $quote['subtotal'] }};
function updateDelivery() {
 const fee = document.querySelector('[name=delivery_type]:checked').value === 'urgent' ? 5000 : 3000;
 document.getElementById('pilot-total').textContent = (subtotal+fee).toLocaleString('ru-RU')+' ₸';
 document.getElementById('expected_total').value = (subtotal+fee).toFixed(2);
}
document.querySelectorAll('[name=delivery_type]').forEach(el=>el.addEventListener('change',updateDelivery));
updateDelivery();
document.getElementById('confirm-order').form.addEventListener('submit',()=>{document.getElementById('confirm-order').disabled=true;});
</script>
@endsection
