@php
$next = match($order->status) {
    'new' => ['pending' => 'Согласовать и открыть оплату'],
    'pending' => ['paid' => 'Подтвердить оплату'],
    'paid', 'checking' => ['processing' => 'Начать сборку', 'delivering' => 'Передать в доставку'],
    'processing' => ['delivering' => 'Передать в доставку'],
    'delivering' => ['delivered' => 'Подтвердить доставку'],
    default => [],
};
$hasChanges = $order->changeRequests()->where('status', 'pending')->exists();
@endphp
<div class="card" id="order-actions" style="padding:20px;scroll-margin-top:84px">
<h2 style="font-size:17px;margin-bottom:16px">Следующий шаг</h2>
@if($hasChanges)<p style="margin-bottom:16px">Сначала рассмотрите изменения от родственника.</p>@endif
@foreach($next as $status => $label)
<form method="POST" action="{{ route('admin.orders.status', ['order'=>$order, 'list'=>$listContext]) }}" style="margin-bottom:16px">
@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $status }}">
@if($status === 'pending')
<p>Подтвердите, что состав и доставка согласованы с родственником.</p>
<label style="display:block;margin:12px 0"><input type="checkbox" name="delivery_type" value="standard" required> Доставка на следующий день — 3 000 ₸ согласована</label>
@endif
@if($status === 'paid')
<p>Сверьте поступление {{ number_format($order->total,2,',',' ') }} ₸ в Kaspi Pay.</p>
<div class="form-group"><label for="payment_reference">Идентификатор платежа</label><input id="payment_reference" name="payment_reference" class="form-control" maxlength="100" required value="{{ old('payment_reference') }}"></div>
<label style="display:block;margin:12px 0"><input type="checkbox" name="payment_verified" value="1" required> Поступление денег проверено в Kaspi Pay</label>
@endif
<button type="submit" class="btn btn-primary" style="width:100%" @disabled($hasChanges && in_array($status,['pending','paid']))>{{ $label }}</button>
</form>
@endforeach
@if(!$next)<p>Следующих действий по доставке нет.</p>@endif
@if(in_array($order->status,['new','pending']))
<details><summary style="cursor:pointer">Отменить заявку</summary>
<form method="POST" action="{{ route('admin.orders.status', ['order'=>$order, 'list'=>$listContext]) }}" onsubmit="return confirm('Отменить эту заявку?')" style="margin-top:12px">
@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled">
<label>Причина отмены<textarea name="comment" class="form-control" maxlength="180" required></textarea></label>
<button class="btn btn-secondary" style="margin-top:12px">Подтвердить отмену</button>
</form></details>
@endif
@if(in_array($order->status,['paid','checking','processing','delivering','delivered','refunded']) && (float)$order->refund_amount < (float)$order->total)
<details><summary style="cursor:pointer">Зафиксировать возврат</summary>
<p>{{ $order->prisoner_order_type === 'balance' ? 'Указанная разница будет возвращена на счёт Dosmart.' : 'Сначала выполните возврат в Kaspi Pay. Эта форма только фиксирует результат.' }}</p>
<form method="POST" action="{{ route('admin.orders.status', ['order'=>$order, 'list'=>$listContext]) }}">
@csrf @method('PATCH')<input type="hidden" name="status" value="refunded">
<label>Общая возвращённая сумма, ₸<input class="form-control" name="refund_amount" type="number" step="0.01" min="{{ (float)$order->refund_amount + 0.01 }}" max="{{ $order->total }}" required value="{{ old('refund_amount') }}"></label>
<label>Причина<textarea class="form-control" name="comment" maxlength="180" required>{{ old('comment') }}</textarea></label>
@if($order->prisoner_order_type === 'balance' && (float)$order->delivery_fee > 0)
@if($order->refund_includes_delivery)<p class="small">Доставка уже вошла в прежний возврат.</p>
@else<label style="display:block;margin:12px 0"><input type="checkbox" name="refund_includes_delivery" value="1" @checked(old('refund_includes_delivery'))> В том числе возвращена доставка ({{ number_format((float)$order->delivery_fee, 0, ',', ' ') }} ₸). Без отметки вся сумма считается возвратом за товары — она вернётся в месячный лимит.</label>@endif
@endif
<label style="display:block;margin:12px 0"><input type="checkbox" name="refund_verified" value="1" required> {{ $order->prisoner_order_type === 'balance' ? 'Подтверждаю возврат на счёт Dosmart' : 'Возврат выполнен в Kaspi Pay' }}</label>
<button class="btn btn-secondary">Зафиксировать возврат</button>
</form></details>
@endif
</div>
