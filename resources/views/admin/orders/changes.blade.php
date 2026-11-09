@if($order->changeRequests()->exists())
<div class="card" style="padding:20px;margin-bottom:20px"><h2>Изменения от родственника</h2>
@foreach($order->changeRequests()->latest()->get() as $change)
<div style="padding:16px 0;border-bottom:1px solid var(--border)">
<strong>{{ ['pending'=>'Нужно рассмотреть','approved'=>'Подтверждено','rejected'=>'Отклонено'][$change->status] }}</strong>
@foreach($change->items as $line)<p>{{ $line['name'] }}: {{ $line['before'] }} → {{ $line['quantity'] }}@if(isset($line['price'])) · новый товар, {{ number_format($line['price'], 0, ',', ' ') }} ₸/шт.@endif</p>@endforeach
@if($change->comment)<p>Пожелание: {{ $change->comment }}</p>@endif
@if($change->status === 'pending')
<form method="POST" action="{{ route('admin.orders.changes', ['order' => $order, 'change' => $change->id, 'list' => $listContext]) }}">@csrf
<label class="form-label">Ответ родственнику (будет виден по ссылке)</label><textarea class="form-control" name="response" maxlength="500" rows="2"></textarea>
<p style="font-size:12px">Подтверждение применяет указанные количества по ценам заявки. Проверьте наличие и согласуйте пожелания с родственником. После подтверждения он сможет оплатить обновлённую сумму.</p>
<button class="btn btn-primary" name="decision" value="approved">Подтвердить изменения</button>
<button class="btn btn-secondary" name="decision" value="rejected">Отклонить</button>
</form>
@else<p>{{ $change->response }}</p>@endif
</div>
@endforeach
</div>
@endif
