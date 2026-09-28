@extends('shop.layout')
@section('title', 'Оплата заказа — DOSMART')
@section('content')
<div style="max-width:650px;margin:auto"><div class="eyebrow">ЗАКАЗ ОФОРМЛЕН</div><h1>{{ $order->order_number }}</h1><p class="intro">Статус: <strong>{{ $order->status_label }}</strong></p>
<div class="form-card"><div class="summary-row"><span>Товары</span><span>{{ number_format($order->subtotal,2,',',' ') }} ₸</span></div><div class="summary-row"><span>{{ $order->delivery_type === 'unconfirmed' ? 'Доставка согласовывается' : ($order->delivery_type === 'urgent' ? 'Срочная доставка' : 'Доставка на следующий день') }}</span><span>{{ $order->delivery_type === 'unconfirmed' ? 'Уточняется' : number_format($order->delivery_fee,2,',',' ').' ₸' }}</span></div>
<div class="summary-total" style="margin-bottom:20px"><span>{{ $order->delivery_type === 'unconfirmed' ? 'Товары без доставки' : 'Сумма заказа' }}</span><span>{{ number_format($order->total,2,',',' ') }} ₸</span></div>
@if($order->status === 'pending')
<button class="btn btn-ghost" type="button" onclick="copyPayment('amount')">Скопировать сумму</button>
<button class="btn btn-ghost" type="button" onclick="copyPayment('number')">Скопировать номер</button>
<ol style="margin:24px 0;padding-left:20px;line-height:1.8;font-size:14px"><li>Откройте удалённую оплату Kaspi и укажите точную сумму.</li><li>Если есть поле комментария, укажите {{ $order->order_number }}.</li><li>Отправьте квитанцию в WhatsApp DoSmart вместе с номером заказа.</li><li>Дождитесь ответа «Принято». Сотрудник сверит платёж в Kaspi Pay.</li></ol>
@if($kaspiUrl)<a class="btn btn-primary btn-block btn-lg" href="{{ $kaspiUrl }}" target="_blank" rel="noopener noreferrer">Оплатить через Kaspi ↗</a>
@else<p class="form-errors">Ссылка Kaspi пока не настроена. Оплату временно принимать нельзя — свяжитесь с сотрудником.</p>@endif
<p class="form-hint" style="margin:12px 0">Если уже оплатили, повторно платить не нужно. Ожидайте проверки сотрудником.</p>
@elseif($order->status === 'new')<p class="intro">Заявка получена. Сотрудник DoSmart свяжется с родственником, согласует доставку и подготовит оплату. Пока оплачивать не нужно.</p>
@elseif($order->status === 'refunded')<p class="intro">Оформлен возврат: {{ number_format($order->refund_amount,2,',',' ') }} ₸. Подробности уточните у сотрудника.</p>
@else<p class="intro">{{ $order->status === 'cancelled' ? 'Заказ отменён. Не оплачивайте его.' : 'Повторная оплата не требуется. Статус обновляется сотрудником.' }}</p>@endif
@if($whatsapp)<a class="btn btn-ghost btn-block" style="margin-top:12px" href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode($order->status === 'new' ? 'Здравствуйте! Хочу уточнить заявку '.$order->order_number : 'Здравствуйте! Заказ '.$order->order_number.'. Сумма '.$order->total.' ₸. Прикрепляю квитанцию.') }}" target="_blank" rel="noopener noreferrer">Написать в WhatsApp DoSmart</a>@else<p class="form-hint">WhatsApp DoSmart пока не настроен.</p>@endif
<p class="form-hint" style="margin-top:20px">Сохраните эту ссылку, чтобы вернуться к статусу заказа. Не передавайте её посторонним.</p>
</div></div>
@endsection
@section('scripts')
<script>
try {
 const saved = JSON.parse(sessionStorage.getItem('dosmart_draft_request'));
 if (saved?.code === @json($order->draft->code)) { saveCart([]); sessionStorage.removeItem('dosmart_draft_request'); }
} catch {}
async function copyPayment(kind) {
 const value = kind === 'amount' ? {{ \Illuminate\Support\Js::from(number_format((float) $order->total,2,'.','')) }} : @json($order->order_number);
 try { await navigator.clipboard.writeText(value); showToast('Скопировано'); }
 catch { window.prompt('Скопируйте текст:',value); }
}
</script>
@endsection
