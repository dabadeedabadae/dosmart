<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Квитанция о зачислении {{ $transfer->application_number }} — Dosmart</title>
<style>
@page { size:80mm auto; margin:0; }
* { box-sizing:border-box; }
body { margin:0; color:#000; background:#eee; font:14px/1.4 Arial,sans-serif; }
.controls { max-width:520px; margin:20px auto; padding:16px; }
.controls button { padding:12px 20px; font:inherit; cursor:pointer; }
.receipt { width:80mm; padding:5mm 3mm 12mm; margin:20px auto; background:#fff; overflow-wrap:anywhere; }
h1 { font-size:25px; text-align:center; margin:0; }
h2 { font-size:17px; margin:10px 0; }
p { margin:5px 0; }
.center { text-align:center; } .small { font-size:11px; }
.section { border-top:1px dashed #000; padding-top:8px; margin-top:10px; }
.total { font-size:19px; font-weight:bold; display:flex; justify-content:space-between; }
@media print { body{background:#fff;} .controls{display:none;} .receipt{margin:0;} }
</style>
</head>
<body>
<div class="controls"><button type="button" onclick="window.print()">Печать / сохранить PDF</button></div>
<main class="receipt">
<h1>Dosmart</h1>
<p class="center small">Квитанция о зачислении на баланс · не фискальный чек</p>
<h2>№ {{ $transfer->application_number }}</h2>
<div class="section">
<p><strong>Отправитель:</strong><br>{{ $transfer->source === 'relative' ? ($transfer->sender_name ?: 'Родственник') : 'Перевод учреждения по заявлению осуждённого' }}</p>
<p><strong>Получатель:</strong><br>{{ $prisoner->full_name }}</p>
<p>{{ $prisoner->institution->name }}@if($prisoner->squad), отряд {{ $prisoner->squad }}@endif</p>
</div>
<div class="section">
<div class="total"><span>Зачислено</span><span>{{ number_format($transfer->amount_minor / 100, 2, ',', ' ') }} тг</span></div>
<p class="small">Платёж: {{ $transfer->payment_reference }}</p>
<p class="small">Дата зачисления: {{ $transfer->confirmed_at?->timezone('Asia/Almaty')->format('d.m.Y H:i') }} (UTC+5)</p>
@if($staff)<p class="small">Зачислил: {{ $staff }}</p>@endif
@if($transfer->status === 'reversed')<p><strong>Зачисление отменено (сторно):</strong> {{ $transfer->close_reason }}</p>@endif
</div>
</main>
</body>
</html>
