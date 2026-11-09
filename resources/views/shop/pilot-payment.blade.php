@extends('shop.layout')
@section('title', 'Ваша заявка — Dosmart')
@section('body-class', 'checkout-page')
@section('content')

<style>
#unsaved-payment[hidden], #apply-update[hidden], #order-payment[hidden], .edit-product[hidden], .edit-pages[hidden], .edit-photo img[hidden] { display:none !important; }
.edit-intro{line-height:1.65;color:var(--muted,#66756f);margin:14px 0 24px}.edit-products{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.edit-product{border:1px solid var(--border,#dfe6e1);border-radius:18px;overflow:hidden;background:var(--surface,#fff);padding:12px;display:flex;flex-direction:column;gap:12px}.edit-photo{height:150px;border-radius:12px;background:#f5f7f3;display:grid;place-items:center;overflow:hidden;color:#7a857d;font-size:13px}.edit-photo img{width:100%;height:150px;max-height:150px;object-fit:contain;display:block}.edit-product-info h3{font-size:16px;line-height:1.4;margin:0 0 6px}.edit-product-info p{margin:0;font-weight:700}.edit-product-info span{font-weight:400;color:#69766e}.edit-product-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:auto;flex-wrap:wrap}.edit-stepper{display:flex;align-items:center;border:1px solid var(--border,#dfe6e1);border-radius:10px;overflow:hidden}.edit-stepper button{width:40px;min-height:44px;background:#f3f6f2;border:0;font-size:23px;cursor:pointer;color:inherit}.edit-stepper button:disabled{opacity:.35;cursor:default}.edit-stepper input{width:48px;min-height:44px;text-align:center;border:0;background:transparent;font:inherit;color:inherit;appearance:textfield;-moz-appearance:textfield}.edit-stepper input::-webkit-inner-spin-button{appearance:none}.edit-remove{border:0;background:transparent;padding:8px;color:#8c4640;text-align:left;text-decoration:underline;cursor:pointer;font:inherit;font-size:13px}.edit-product.is-removed .edit-photo{opacity:.4}.edit-product.is-removed .edit-remove{color:#266749}.edit-pages{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:20px 0}.edit-pages .btn{min-height:44px}.edit-footer{border-top:1px solid var(--border,#dfe6e1);padding-top:24px;margin-top:24px}.edit-optional{font-size:12px;font-weight:400;color:#798079}.edit-estimate{background:#f1f6f1;border-radius:14px;padding:16px;margin:20px 0 12px}.edit-estimate>div{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}.edit-estimate strong{font-size:22px}.edit-estimate p{font-size:12px;line-height:1.6;margin:8px 0 0;color:#59675d}.edit-footer [data-edit-submit]{margin-top:16px}.edit-footer button:disabled{opacity:.5;cursor:default}
@media(max-width:520px){.edit-products{grid-template-columns:1fr}.edit-photo{height:160px}.edit-product-info h3{font-size:17px}}
</style>
<div class="checkout-wrap" style="max-width:780px">
@if(session('success'))<p class="intro" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="form-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<p id="status-refresh" aria-live="polite" class="form-hint">Статус обновляется автоматически каждые 15 секунд.</p>
<button type="button" class="btn btn-ghost" onclick="applyPendingUpdate()" id="apply-update" hidden>Показать обновление</button>
<div id="order-details">@include('shop.partials.order-details')</div>
<p class="form-hint" style="margin-top:20px">Сохраните личную ссылку. По ней доступны состав, изменения, оплата и статус заявки. Не передавайте её посторонним.</p>
</div>
@endsection
@section('scripts')
<script>
let revision = @json($revision), busy = false, dirty = false, pendingUpdate = null;
document.addEventListener('input', event => { if(event.target.closest('[data-change-form]')) dirty = true; });
function updateDetails(data) {
 document.getElementById('order-details').innerHTML = data.html;
 revision = data.revision; dirty = false; pendingUpdate = null;
 initOrderEditor();
 document.getElementById('apply-update').hidden = true;
 document.getElementById('status-refresh').textContent = 'Статус обновлён.';
}
function applyPendingUpdate() {
 if(pendingUpdate && (!dirty || window.confirm('Введённые изменения ещё не отправлены. Показать новую версию и очистить ввод?'))) updateDetails(pendingUpdate);
}
async function refreshOrderStatus() {
 if(document.hidden || busy) return;
 busy = true;
 try {
  const response = await fetch(@json(route('pilot.status', $order->draft->code))+'?revision='+encodeURIComponent(revision), {headers:{Accept:'application/json'}, cache:'no-store', signal:AbortSignal.timeout(10000)});
  if(!response.ok) throw new Error('status');
  const data = await response.json();
  if(data.revision !== revision && data.html) {
   const editing = dirty || document.activeElement?.closest('[data-change-form]') || document.getElementById('change-order')?.open;
   if(editing) {
    pendingUpdate = data;
    const payment = document.getElementById('order-payment');
    if(payment) payment.hidden = true;
    document.getElementById('status-refresh').textContent = 'Есть обновление заявки. Ваш ввод сохранён на экране; проверьте обновление перед отправкой.';
    document.getElementById('apply-update').hidden = false;
   } else { updateDetails(data); document.getElementById('status-refresh').textContent = 'Статус обновлён.'; }
  }
 } catch { document.getElementById('status-refresh').textContent = 'Не удалось проверить статус. Повторим автоматически.'; }
 finally { busy = false; }
}
setInterval(refreshOrderStatus,15000);
document.addEventListener('visibilitychange',refreshOrderStatus);
async function copyPayment(value) {
 try { await navigator.clipboard.writeText(value); showToast('Скопировано'); }
 catch { window.prompt('Скопируйте текст:',value); }
}
function discardOrderEdits() {
 if (!window.confirm('Отменить несохранённые изменения?')) return;
 if (pendingUpdate) { updateDetails(pendingUpdate); return; }
 const form = document.querySelector('[data-change-form]');
 form.querySelectorAll('[data-edit-product], [data-add-product]').forEach(card => {card.querySelector('input[type=number]').value = card.dataset.original;});
 form.querySelector('[name=comment]').value = '';
 updateOrderEditor();
}
function updateOrderEditor() {
 const form=document.querySelector('[data-change-form]'); if(!form) return;
 let cents=0, count=0, changed=false, valid=true;
 form.querySelectorAll('[data-edit-product], [data-add-product]').forEach(card=>{
  const input=card.querySelector('input[type=number]'), qty=Number(input.value), original=Number(card.dataset.original);
  const ok=input.value!=='' && Number.isInteger(qty) && qty>=0 && qty<=1000;
  valid=valid && ok;
  const value=ok ? qty : 0;
  count+=value; cents+=Math.round(Number(card.dataset.price)*100)*value;
  changed=changed || value!==original;
  const added=card.hasAttribute('data-add-product');
  if(added) card.querySelectorAll('input[name]').forEach(field=>field.disabled=ok && value===0 && field.type!=='number');
  card.classList.toggle('is-removed',!added && ok && value===0);
  card.querySelector('[data-line-total]').textContent=(Math.round(Number(card.dataset.price)*100)*value/100).toLocaleString('ru-RU')+' ₸';
  card.querySelector('[data-quantity-action=minus]').disabled=value<=0;
  card.querySelector('[data-quantity-action=plus]').disabled=value>=1000;
  card.querySelector('[data-quantity-action=remove]').textContent=value===0 ? (added ? 'Добавить в заявку' : 'Вернуть товар') : 'Убрать из заявки';
 });
 const selected=[...form.querySelectorAll('[data-add-product]')].filter(card=>Number(card.querySelector('input[type=number]').value)>0);
 form.querySelector('[data-added-summary]').textContent=selected.length ? 'Добавляем: '+selected.map(card=>card.dataset.name+' × '+card.querySelector('input[type=number]').value).join(', ') : '';
 const comment=form.querySelector('[name=comment]').value.trim();
 form.querySelector('[data-edit-total]').textContent=((cents+Math.round(Number(form.dataset.fee)*100))/100).toLocaleString('ru-RU')+' ₸';
 dirty = changed || !!comment || !valid;
 const payment = document.getElementById('order-payment');
 if (payment) payment.hidden = dirty || !!pendingUpdate;
 const warning = document.getElementById('unsaved-payment');
 if (warning) warning.hidden = !dirty;
 form.querySelector('[data-edit-submit]').disabled=!valid || count===0 || (!changed && !comment);
 form.querySelector('[data-edit-hint]').textContent=!valid ? 'Количество должно быть целым числом от 0 до 1000.' : count===0 ? 'Оставьте хотя бы один товар. Для отмены всей заявки свяжитесь с сотрудником.' : !changed && !comment ? 'Измените количество или добавьте комментарий.' : 'Изменения будут отправлены сотруднику на проверку.';
}
function showEditPage(form,page) {
 const cards=[...form.querySelectorAll('[data-edit-product]')], pages=Math.max(1,Math.ceil(cards.length/6));
 page=Math.max(0,Math.min(pages-1,page));form.dataset.page=page;
 cards.forEach((card,index)=>card.hidden=Math.floor(index/6)!==page);
 form.querySelector('[data-edit-pagination]').hidden=pages<=1;
 form.querySelector('[data-page-label]').textContent=(page+1)+' / '+pages;
 form.querySelector('[data-edit-page="-1"]').disabled=page===0;
 form.querySelector('[data-edit-page="1"]').disabled=page===pages-1;
}
function initOrderEditor() {
 const form=document.querySelector('[data-change-form]'); if(!form) return;
 showEditPage(form,0); showCatalogPage(0); updateOrderEditor();
}
document.addEventListener('input',event=>{if(event.target.closest('[data-change-form]')) updateOrderEditor();});
document.addEventListener('click',event=>{
 const action=event.target.closest('[data-quantity-action]');
 if(action){
  const card=action.closest('[data-edit-product], [data-add-product]'),input=card.querySelector('input[type=number]');
  const value=Number(input.value)||0;
  input.value=action.dataset.quantityAction==='remove' ? (value===0 ? Math.max(1,Number(card.dataset.original)) : 0) : Math.max(0,Math.min(1000,value+(action.dataset.quantityAction==='plus'?1:-1)));
  input.dispatchEvent(new Event('input',{bubbles:true}));
 }
 const pager=event.target.closest('[data-edit-page]');
 if(pager){const form=pager.closest('form');showEditPage(form,Number(form.dataset.page)+Number(pager.dataset.editPage));form.querySelector('.edit-products').scrollIntoView({behavior:'smooth',block:'start'});}
});
function showCatalogPage(page=0) {
 const catalog=document.querySelector('[data-catalog]'); if(!catalog) return;
 const query=catalog.querySelector('[data-catalog-search]').value.trim().toLocaleLowerCase('ru');
 const category=catalog.querySelector('[data-catalog-category]').value;
 const cards=[...catalog.querySelectorAll('[data-add-product]')];
 const matches=cards.filter(c=>(!category || c.dataset.category===category) && c.dataset.name.toLocaleLowerCase('ru').includes(query));
 const pages=Math.max(1,Math.ceil(matches.length/6));page=Math.max(0,Math.min(pages-1,page));catalog.dataset.page=page;
 cards.forEach(c=>c.hidden=true); matches.slice(page*6,page*6+6).forEach(c=>c.hidden=false);
 catalog.querySelector('[data-catalog-count]').textContent=matches.length ? 'Товаров: '+matches.length : 'Товары не найдены';
 catalog.querySelector('[data-catalog-page-label]').textContent=(page+1)+' / '+pages;
 catalog.querySelector('[data-catalog-page="-1"]').disabled=page===0;
 catalog.querySelector('[data-catalog-page="1"]').disabled=page===pages-1;
}
document.addEventListener('input',event=>{if(event.target.matches('[data-catalog-search]')) showCatalogPage();});
document.addEventListener('change',event=>{if(event.target.matches('[data-catalog-category]')) showCatalogPage();});
document.addEventListener('click',event=>{const button=event.target.closest('[data-catalog-page]');if(button) showCatalogPage(Number(button.closest('[data-catalog]').dataset.page)+Number(button.dataset.catalogPage));});
document.addEventListener('submit',event=>{if(event.target.matches('[data-change-form]')) event.target.querySelectorAll('[data-add-product]').forEach(card=>{if(Number(card.querySelector('input[type=number]').value)===0) card.querySelectorAll('input[name]').forEach(input=>input.disabled=true);});});
initOrderEditor();

try {
 const saved = JSON.parse(sessionStorage.getItem('dosmart_draft_request'));
 if(saved?.code === @json($order->draft->code)) { saveCart([]); sessionStorage.removeItem('dosmart_draft_request'); }
} catch {}
</script>
@endsection
