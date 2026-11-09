@extends('admin.layouts.app')
@section('title', 'Сводка и закупка')
@section('content')
<div class="page-header"><h1 class="page-title">Сводка и закупка</h1><a href="{{ route('admin.orders.index') }}">Все заявки</a></div>
<form method="get" class="card" style="padding:20px;display:flex;gap:16px;flex-wrap:wrap;align-items:end">
<label>Дата (Казахстан)<input class="form-control" type="date" name="date" value="{{ $date }}" required></label>
<label>Какие заявки показать<select class="form-control" name="scope"><option value="created" @selected($scope === 'created')>Поступившие в этот день</option><option value="activity" @selected($scope === 'activity')>Со сменой статуса в этот день</option></select></label>
<label>Статус<select class="form-control" name="status"><option value="">Все статусы</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></label><button class="btn btn-primary">Показать</button>
</form>
<p style="margin:16px 0">Состояние заявок показано на текущий момент. «Оплачены» и «Доставлены» могут пересекаться. Сообщение клиента об оплате не считается подтверждённым платежом.</p>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">@foreach($stats as $label => $value)<div class="card" style="padding:16px;min-width:140px"><strong style="font-size:26px">{{ $value }}</strong><div>{{ $label }}</div></div>@endforeach</div>
<form method="get" action="{{ route('admin.orders.report') }}#shopping" id="purchase-selection">
<input type="hidden" name="status" value="{{ $status }}">
<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="scope" value="{{ $scope }}">
<div class="card" style="padding:20px;margin-bottom:20px"><h2>Заявки за {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</h2>
<p>Можно выбрать любые заявки из текущей выборки. Оператор сам проверяет статусы и состав. Формирование списка не меняет заявки.</p>
<div class="purchase-toolbar"><label><input type="checkbox" id="select-all"> Выбрать все доступные</label><strong id="selection-count" aria-live="polite">Выбрано: {{ $selected->count() }}</strong><button type="button" class="btn btn-ghost" id="clear-selection">Снять выделение</button></div>
<p class="form-hint">Нажмите на строку или поставьте галочку. Доступны все статусы, включая неоплаченные и уже отправленные заявки.</p>

<div class="table-wrap"><table><thead><tr><th>Закупить</th><th>Заявка</th><th>Получатель</th><th>Статус</th><th>Оплата</th><th>Действия за день</th></tr></thead><tbody>
@forelse($orders as $order)<tr class="purchase-row {{ $selected->contains($order->id) ? 'is-selected' : '' }}">
<td><label class="purchase-choice"><input class="purchase-order" type="checkbox" name="orders[]" value="{{ $order->id }}" @checked($selected->contains($order->id)) aria-label="Выбрать заявку {{ $order->order_number }}"><span>Выбрать</span></label></td>
<td><a href="{{ route('admin.orders.show', $order) }}" target="_blank" rel="noopener">{{ $order->order_number }}</a></td><td>{{ $order->prisoner_name }}<br>{{ $order->institution_name }} · Отряд {{ $order->squad_number ?: '—' }}</td><td>{{ $order->status_label }}</td><td>{{ $order->payment_status_label }}</td>
<td>@forelse($order->statusHistory as $event)<div>{{ $event->created_at->timezone('Asia/Almaty')->format('H:i') }} · {{ $event->comment }}</div>@empty — @endforelse</td></tr>
@empty<tr><td colspan="6">За выбранный день заявок нет.</td></tr>@endforelse
</tbody></table></div><button class="btn btn-primary" id="build-shopping" style="margin-top:16px">Собрать список закупки</button></div>
</form>
<section class="card" id="shopping" style="padding:20px"><h2>Список закупки · {{ $date }}</h2>
@if($shoppingOrders->isNotEmpty())
<p style="margin:12px 0"><strong>Заявки ({{ $shoppingOrders->count() }}):</strong> {{ $shoppingOrders->pluck('order_number')->implode(', ') }}</p>
<p style="margin-bottom:12px">Курьер: ____________________ &nbsp; Дата закупки: ____________________</p>
@else<p class="no-print" style="margin:12px 0">Выберите заявки выше и нажмите «Собрать список закупки».</p>@endif
<p>Количество — в товарных единицах заявки (упаковки, штуки и т. п.), без пересчёта в килограммы. Цены магазина не являются закупочными.</p>
<p class="no-print">Можно поправить количество и дописать позиции. Эти заметки не меняют заявки и сохраняются только на этой странице до её обновления — распечатайте готовый список.</p>
<div class="table-wrap"><table><thead><tr><th>Товар</th><th>Количество</th><th>Заявки / примечание</th><th>Куплено</th></tr></thead><tbody id="shopping-rows">
@foreach($shopping as $row)<tr><td contenteditable="true">{{ $row['name'] }}</td><td contenteditable="true">{{ $row['quantity'] }}</td><td contenteditable="true">{{ implode(', ', $row['orders']) }}</td><td><span class="purchase-tick">□</span></td></tr>@endforeach
</tbody></table></div>
<div class="no-print" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px"><button type="button" class="btn" id="add-row">Добавить свою позицию</button><button type="button" class="btn btn-ghost" id="copy-shopping" @disabled($shoppingOrders->isEmpty())>Скопировать список</button><span id="copy-status" role="status"></span><button type="button" class="btn btn-primary" id="print-shopping" onclick="window.print()" @disabled($shoppingOrders->isEmpty())>Распечатать список</button></div>
</section>
<style>
.purchase-choice{display:inline-flex;align-items:center;gap:10px;min-height:44px;cursor:pointer;font-size:13px}.purchase-choice input,#select-all{appearance:auto;-webkit-appearance:checkbox;width:22px;height:22px;min-width:22px;min-height:22px;flex-shrink:0;accent-color:var(--accent);margin:0}.purchase-choice.is-unavailable{cursor:default;color:var(--text-secondary)}.purchase-choice input:disabled{opacity:.65}
.purchase-toolbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin:16px 0}.purchase-toolbar label{display:flex;align-items:center;gap:8px;min-height:44px}.purchase-row:has(.purchase-order){cursor:pointer}.purchase-row.is-selected,.purchase-row.is-selected:hover{background:var(--accent-wash)}.purchase-row.is-selected td:first-child{box-shadow:inset 4px 0 var(--accent)}.purchase-tick{font-size:23px}#build-shopping:disabled,#print-shopping:disabled{opacity:.5;cursor:default}
@page{size:A4;margin:12mm}
@media print {body{display:block;background:white;color:black}.sidebar{display:none!important}.main{margin:0;padding:0;min-height:0}.main>:not(#shopping){display:none!important}#shopping{position:static;width:100%;border:0;box-shadow:none;padding:0!important;overflow:visible}#shopping .no-print{display:none!important}#shopping .table-wrap{overflow:visible}#shopping table{display:table}#shopping thead{display:table-header-group}#shopping tbody{display:table-row-group}#shopping tr{display:table-row;break-inside:avoid}#shopping td{display:table-cell;border:1px solid #bbb;padding:8px}#shopping th{color:black;border:1px solid #bbb;white-space:normal}#shopping h2{font-size:20px}#shopping p{font-size:11px;margin:10px 0} }
@media print {
 #shopping table{width:100%;table-layout:fixed;font-size:17px;line-height:1.15}
 #shopping td{font-size:17px;line-height:1.15;padding:3px 6px;height:auto;min-height:0;vertical-align:top;overflow-wrap:anywhere}
 #shopping th{font-size:13px;line-height:1.15;letter-spacing:0;padding:5px 6px;text-transform:none}
 #shopping th:nth-child(1){width:50%}#shopping th:nth-child(2){width:13%}#shopping th:nth-child(3){width:27%}#shopping th:nth-child(4){width:10%}
 #shopping td:nth-child(2),#shopping td:nth-child(4){text-align:center}
 #shopping .purchase-tick{font-size:17px;line-height:1}
 #shopping [contenteditable],#shopping [contenteditable] *{min-height:0!important;outline:0!important;box-shadow:none!important}
 #shopping h2{font-size:19px;margin:0 0 6px}#shopping p{margin:5px 0!important;line-height:1.25}
}
</style>
<script>
const purchaseBoxes=Array.from(document.querySelectorAll('.purchase-order'));
const selectAll=document.getElementById('select-all');
function updateSelection(){const count=purchaseBoxes.filter(box=>box.checked).length;document.getElementById('selection-count').textContent='Выбрано: '+count;selectAll.checked=count>0&&count===purchaseBoxes.length;selectAll.indeterminate=count>0&&count<purchaseBoxes.length;selectAll.disabled=!purchaseBoxes.length;document.getElementById('build-shopping').disabled=!count;purchaseBoxes.forEach(box=>box.closest('tr').classList.toggle('is-selected',box.checked));}
selectAll.addEventListener('change',function(){purchaseBoxes.forEach(box=>box.checked=this.checked);updateSelection()});
document.getElementById('clear-selection').addEventListener('click',()=>{purchaseBoxes.forEach(box=>box.checked=false);updateSelection()});
purchaseBoxes.forEach(box=>{box.addEventListener('change',updateSelection);box.closest('tr').addEventListener('click',event=>{if(event.target.closest('a,button,input,label,select,textarea'))return;box.checked=!box.checked;updateSelection()})});
updateSelection();
document.getElementById('add-row').addEventListener('click',function(){const tr=document.createElement('tr');['Название товара','1','','□'].forEach((text,index)=>{const td=document.createElement('td');td.contentEditable=index<3?'true':'false';td.textContent=text;tr.appendChild(td)});document.getElementById('shopping-rows').appendChild(tr);tr.firstChild.focus()});
document.getElementById('copy-shopping').addEventListener('click',async()=>{
const lines=Array.from(document.querySelectorAll('#shopping-rows tr')).map(row=>Array.from(row.cells).slice(0,3).map(cell=>cell.innerText.trim()).join(' — '));
const text=document.querySelector('#shopping h2').innerText+'\n'+lines.join('\n');
try { if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(text);}else{const area=document.createElement('textarea');area.value=text;area.style.position='fixed';area.style.top='0';document.body.appendChild(area);area.focus();area.select();const ok=document.execCommand('copy');area.remove();if(!ok)throw Error('copy');}document.getElementById('copy-status').textContent='Список скопирован'; }catch(error){document.getElementById('copy-status').textContent='Не удалось скопировать. Выделите текст списка вручную.';}
});
window.addEventListener('pageshow',updateSelection);
</script>
@endsection
