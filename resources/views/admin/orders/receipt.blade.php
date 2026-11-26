<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Заявка {{ $order->order_number }} — печать DoSmart</title>
<style>
@page { size:80mm auto; margin:0; }
* { box-sizing:border-box; }
body { margin:0; color:#000; background:#eee; font:14px/1.4 Arial,sans-serif; }
.controls { max-width:520px; margin:20px auto; padding:16px; }
.controls button { padding:12px 20px; font:inherit; cursor:pointer; }
.receipt { width:80mm; padding:5mm 3mm 12mm; margin:20px auto; background:#fff; overflow-wrap:anywhere; }
h1 { font-size:25px; text-align:center; margin:0; }
h2 { font-size:18px; margin:10px 0; }
p { margin:5px 0; }
.center { text-align:center; }
.small { font-size:11px; }
.section { border-top:1px dashed #000; padding-top:8px; margin-top:10px; }
.item { margin:10px 0; break-inside:avoid; }
.row { display:flex; justify-content:space-between; gap:8px; }
.amount { white-space:nowrap; }
.total { font-size:19px; font-weight:bold; }
.signature { margin-top:18px; padding-top:12px; border-top:1px dashed #000; break-inside:avoid; }
.signature-line { display:inline-block; width:46mm; border-bottom:1px solid #000; height:10mm; vertical-align:bottom; }
@media print { .receipt + .receipt { break-before:page; page-break-before:always; } }
@media print { body{background:#fff;} .controls{display:none;} .receipt{margin:0;} }
</style>
</head>
<body>
@php($receiptCopies = ($publicReceipt ?? false) ? 1 : 3)
<div class="controls"><button type="button" onclick="window.print()">Печать / сохранить PDF</button><p>Для электронного файла выберите «Сохранить как PDF». Для принтера выберите бумагу 80 мм, масштаб 100%. Отключите колонтитулы браузера. @if($receiptCopies > 1)Подготовлено {{ $receiptCopies }} экземпляра. В окне печати оставьте 1 копию, чтобы распечатать комплект один раз.@else Количество экземпляров задаётся в окне печати.@endif</p></div>
@for($copy = 1; $copy <= $receiptCopies; $copy++)
<main class="receipt">
@php($money = fn($value) => number_format((float)$value, 2, ',', ' ').' тг')
<h1>Dosmart</h1>
@if($receiptCopies > 1)<p class="center small">Экземпляр {{ $copy }} из {{ $receiptCopies }}</p>@endif
<p class="center small">Лист заказа · не фискальный чек</p>
<h2>Заявка № {{ $order->order_number }}</h2>
@unless($publicReceipt ?? false)
<p><strong>Получатель (ФИО):</strong><br>{{ $order->prisoner_name ?: 'Не указан' }}</p>
<p><strong>Учреждение:</strong> {{ $order->institution_name ?: 'Не указано' }}</p>
<p><strong>Отряд:</strong> {{ $order->squad_number ?: 'Не указан' }}</p>
@endunless
<p class="small">Создана: {{ $order->created_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }} (UTC+5)</p>
<p><strong>Статус:</strong> {{ $order->status_label }}</p>
<p><strong>Оплата:</strong> {{ $order->payment_status_label }}</p>
<div class="section">
@foreach($order->items as $item)
<div class="item"><strong>{{ $item->product_name }}</strong><div class="row"><span>{{ $item->quantity }} × {{ $money($item->price) }}</span><span class="amount">{{ $money($item->total) }}</span></div></div>
@endforeach
</div>
<div class="section">
<div class="row"><span>Товары</span><span class="amount">{{ $money($order->subtotal) }}</span></div>
@if($order->status === 'new')
<p>Доставка — {{ number_format((float) config('pilot.delivery_fee',3000),0,',',' ') }} ₸.</p>
<p>Доставка и итоговая сумма пока не согласованы. Не оплачивайте до подтверждения сотрудником.</p>
@else
<div class="row"><span>Доставка</span><span class="amount">{{ $money($order->delivery_fee) }}</span></div>
<div class="row total"><span>Итого</span><span class="amount">{{ $money($order->total) }}</span></div>
@endif
@if((float)$order->refund_amount > 0)<p>Возвращено: {{ $money($order->refund_amount) }}</p>@endif
</div>
<p class="section small center">Связь с магазином: {{ config('pilot.service_hours') }}<br>{{ config('pilot.service_timezone_label') }}</p>
<div class="signature">
@unless($publicReceipt ?? false)
<p><strong>Кому заказали (ФИО):</strong><br>{{ $order->prisoner_name ?: '____________________________' }}</p>
<p><strong>От кого (ФИО):</strong><br>{{ $order->prisoner_order_type === 'balance' ? 'Сам получатель — оплата со счёта Dosmart' : ($order->relative_name ?: '____________________________') }}</p>
@endunless
<strong>ПОЛУЧИЛ</strong> <span class="signature-line"></span><p class="small center">подпись получателя</p></div>
</main>
@endfor
</body>
</html>
