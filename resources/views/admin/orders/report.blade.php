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
@php($tg = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₸')
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px">@foreach($stats as $label => $value)<div class="card" style="padding:16px;min-width:140px"><strong style="font-size:26px">{{ $value }}</strong><div>{{ $label }}</div></div>@endforeach</div>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">@foreach($money as $label => $value)<div class="card" style="padding:16px;min-width:170px"><strong style="font-size:22px;white-space:nowrap">{{ $tg($value) }}</strong><div>{{ $label }}</div></div>@endforeach</div>
<form method="get" action="{{ route('admin.orders.report') }}#shopping" id="purchase-selection">
<input type="hidden" name="status" value="{{ $status }}">
<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="scope" value="{{ $scope }}">
<div class="card" style="padding:20px;margin-bottom:20px"><h2>Заявки за {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</h2>
<p>Можно выбрать любые заявки из текущей выборки. Оператор сам проверяет статусы и состав. Формирование списка не меняет заявки.</p>
<div class="purchase-toolbar"><label><input type="checkbox" id="select-all"> Выбрать все доступные</label><strong id="selection-count" aria-live="polite">Выбрано: {{ $selected->count() }}</strong><button type="button" class="btn btn-ghost" id="clear-selection">Снять выделение</button></div>
<p class="form-hint">Нажмите на строку или поставьте галочку. Доступны все статусы, включая неоплаченные и уже отправленные заявки.</p>

<div class="table-wrap"><table><thead><tr><th>Закупить</th><th>Заявка</th><th>Получатель</th><th>Товары</th><th>Сумма</th><th>Статус</th><th>Оплата</th><th>Действия за день</th></tr></thead><tbody>
@forelse($orders as $order)<tr class="purchase-row {{ $selected->contains($order->id) ? 'is-selected' : '' }}">
<td><label class="purchase-choice"><input class="purchase-order" type="checkbox" name="orders[]" value="{{ $order->id }}" @checked($selected->contains($order->id)) aria-label="Выбрать заявку {{ $order->order_number }}"><span>Выбрать</span></label></td>
<td><a href="{{ route('admin.orders.show', $order) }}" target="_blank" rel="noopener">{{ $order->order_number }}</a></td><td>{{ $order->prisoner_name }}<br>{{ $order->institution_name }} · Отряд {{ $order->squad_number ?: '—' }}</td><td style="white-space:nowrap">{{ $order->items->count() }} поз. · {{ $order->items->sum('quantity') }} шт.</td><td style="white-space:nowrap">{{ $tg($order->total) }}@if((float) $order->delivery_fee > 0)<br><small>доставка {{ $tg($order->delivery_fee) }}</small>@endif</td><td>{{ $order->status_label }}</td><td>{{ $order->payment_status_label }}</td>
<td>@forelse($order->statusHistory as $event)<div>{{ $event->created_at->timezone('Asia/Almaty')->format('H:i') }} · {{ $event->comment }}</div>@empty — @endforelse</td></tr>
@empty<tr><td colspan="8">За выбранный день заявок нет.</td></tr>@endforelse
</tbody></table></div><button class="btn btn-primary" id="build-shopping" style="margin-top:16px">Собрать список закупки</button></div>
</form>
<section class="card" id="shopping" style="padding:20px"><h2>Список закупки · {{ $date }}</h2>
@if($shoppingOrders->isNotEmpty())
<p style="margin:12px 0"><strong>Заявки ({{ $shoppingOrders->count() }}):</strong> {{ $shoppingOrders->pluck('order_number')->implode(', ') }}</p>
<p style="margin-bottom:12px">Курьер: ____________________ &nbsp; Дата закупки: ____________________</p>
@else<p class="no-print" style="margin:12px 0">Выберите заявки выше и нажмите «Собрать список закупки».</p>@endif
<p>Количество — в товарных единицах заявки (упаковки, штуки и т. п.), без пересчёта в килограммы. Цена — цена магазина в заявке (по ней платил клиент), это не закупочная цена. Доставка в суммы списка не входит.</p>
<p class="no-print">Можно поправить количество и дописать позиции. Эти заметки не меняют заявки и сохраняются только на этой странице до её обновления — распечатайте готовый список.</p>
<div class="table-wrap"><table><thead><tr><th>Товар</th><th>Количество</th><th>Цена</th><th>Сумма</th><th>Заявки / примечание</th><th>Куплено</th></tr></thead><tbody id="shopping-rows">
@foreach($shopping as $row)@php($prices = array_values($row['prices']))<tr data-price="{{ count($prices) === 1 ? $prices[0] : '' }}"><td contenteditable="true">{{ $row['name'] }}</td><td contenteditable="true" class="qty">{{ $row['quantity'] }}</td><td style="white-space:nowrap">{{ count($prices) === 1 ? $tg($prices[0]) : $tg(min($prices)).'–'.$tg(max($prices)) }}</td><td class="sum" style="white-space:nowrap">{{ $tg($row['total']) }}</td><td contenteditable="true">{{ implode(', ', $row['orders']) }}</td><td><span class="purchase-tick">□</span></td></tr>@endforeach
</tbody>@if($shopping)<tfoot><tr><th>Итого: {{ $shoppingTotals['positions'] }} поз.</th><th id="total-qty">{{ $shoppingTotals['quantity'] }}</th><th></th><th id="total-sum" style="white-space:nowrap">{{ $tg($shoppingTotals['total']) }}</th><th colspan="2"></th></tr></tfoot>@endif</table></div>
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
 #shopping th:nth-child(1){width:38%}#shopping th:nth-child(2){width:9%}#shopping th:nth-child(3){width:12%}#shopping th:nth-child(4){width:13%}#shopping th:nth-child(5){width:20%}#shopping th:nth-child(6){width:8%}
 #shopping td:nth-child(2),#shopping td:nth-child(6){text-align:center}#shopping td:nth-child(3),#shopping td:nth-child(4){text-align:right}
 #shopping tfoot{display:table-footer-group}#shopping tfoot th{border:1px solid #bbb;font-size:15px}
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
document.getElementById('add-row').addEventListener('click',function(){const tr=document.createElement('tr');['Название товара','1','','','','□'].forEach((text,index)=>{const td=document.createElement('td');td.contentEditable=[0,1,4].includes(index)?'true':'false';if(index===1)td.className='qty';if(index===3)td.className='sum';td.textContent=text;tr.appendChild(td)});document.getElementById('shopping-rows').appendChild(tr);tr.firstChild.focus()});
document.getElementById('copy-shopping').addEventListener('click',async()=>{
const lines=Array.from(document.querySelectorAll('#shopping-rows tr')).map(row=>{const c=Array.from(row.cells).map(cell=>cell.innerText.trim());return [c[0],c[1]+' шт.',c[2],c[3],c[4]].filter(Boolean).join(' — ')});
const total=document.getElementById('total-sum');
const text=document.querySelector('#shopping h2').innerText+'\n'+lines.join('\n')+(total?'\nИтого: '+document.getElementById('total-qty').innerText+' шт. на '+total.innerText:'');
try { if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(text);}else{const area=document.createElement('textarea');area.value=text;area.style.position='fixed';area.style.top='0';document.body.appendChild(area);area.focus();area.select();const ok=document.execCommand('copy');area.remove();if(!ok)throw Error('copy');}document.getElementById('copy-status').textContent='Список скопирован'; }catch(error){document.getElementById('copy-status').textContent='Не удалось скопировать. Выделите текст списка вручную.';}
});
const tenge=v=>new Intl.NumberFormat('ru-RU',{maximumFractionDigits:0}).format(v)+' ₸';
// Поправили количество — пересчитываем сумму строки (если у товара одна цена) и итоги.
function recalc(){let qty=0,sum=0;document.querySelectorAll('#shopping-rows tr').forEach(row=>{const q=parseFloat((row.querySelector('.qty')?.innerText||'').replace(',','.'))||0;qty+=q;const price=parseFloat(row.dataset.price||'');const cell=row.querySelector('.sum');if(!isNaN(price)&&cell){cell.textContent=tenge(q*price);}const s=parseFloat((cell?.innerText||'').replace(/[^0-9,]/g,'').replace(',','.'))||0;sum+=s});const tq=document.getElementById('total-qty'),ts=document.getElementById('total-sum');if(tq)tq.textContent=qty;if(ts)ts.textContent=tenge(sum)}
document.getElementById('shopping-rows').addEventListener('input',event=>{if(event.target.classList.contains('qty'))recalc()});
window.addEventListener('pageshow',updateSelection);
</script>
@endsection
