@extends('shop.layout')
@section('title', 'Корзина — DOSMART')

@section('content')
<div style="max-width:780px;margin:0 auto">

    <div class="breadcrumb">
        <a href="{{ route('shop.index') }}">Каталог</a>
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="m9 18 6-6-6-6"/>
        </svg>
        Корзина
    </div>

    <div id="cart-empty" class="empty" style="display:none">
        <div class="empty-icon">🛒</div>
        <h3>Корзина пуста</h3>
        <p>Добавьте товары из каталога</p>
        <a href="{{ route('shop.index') }}" class="btn btn-primary" style="margin-top:16px;display:inline-flex">
            Перейти в каталог
        </a>
    </div>

    <div id="cart-content">
        <h1 class="cart-title">Ваша корзина</h1><p class="cart-intro">Проверьте товары и количество. Доставку выберете при оформлении.</p>

        <div class="cart-columns" style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

            <div class="form-card" style="padding:0;overflow:hidden">
                <table class="cart-table" id="cart-table">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th style="text-align:center">Кол-во</th>
                            <th style="text-align:right">Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cart-rows"></tbody>
                </table>
            </div>

            <div class="summary-card">
                <div style="font-size:15px;font-weight:800;margin-bottom:12px">Итого</div>
                <div class="summary-row">
                    <span>Товары</span>
                    <span id="sum-subtotal">0 ₸</span>
                </div>
                <div class="summary-row">
                    <span>Доставка</span>
                    <span>3 000 ₸</span>
                </div>
                <div class="summary-total">
                    <span>К оплате</span>
                    <span id="sum-total">0 ₸</span>
                </div>
                <button type="button" onclick="prepareDraft(false)" class="btn btn-primary btn-block btn-lg draft-button" style="margin-top:16px">Оформить заказ</button>
                <button type="button" onclick="prepareDraft(true)" class="btn btn-ghost btn-block draft-button" style="margin-top:10px">Отправить родственнику</button>
                <p class="form-hint">Доставку 3 000 или 5 000 ₸ можно выбрать при оформлении.</p>
            </div>

        </div>
    </div>
</div>
<section id="share-draft" class="form-card" style="display:none;margin-top:24px"><h2>Корзина готова к отправке</h2><p class="intro">Скопируйте сообщение и отправьте родственнику в чат. Само сообщение ещё не отправлено.</p><label for="chat-text" class="form-label">Сообщение для родственника</label><textarea id="chat-text" class="form-control" rows="12" readonly></textarea><button class="btn btn-primary" style="margin-top:16px" onclick="copyDraft()">Скопировать сообщение</button><a id="draft-link" class="btn btn-ghost">Открыть корзину</a><button type="button" class="btn btn-ghost draft-button" style="margin-top:12px" onclick="sessionStorage.removeItem('dosmart_draft_request'); prepareDraft(true)">Создать новую ссылку</button></section>
@endsection

@section('scripts')
<script>
const DELIVERY_FEE = 3000;
async function prepareDraft(share) {
 const items = getCart().map(i=>({product_id:i.product_id,quantity:i.quantity}));
 if (!items.length) return showToast('Корзина пуста');
 const fingerprint = JSON.stringify(items);
 let saved;
 try { saved = JSON.parse(sessionStorage.getItem('dosmart_draft_request')); } catch {}
 if (!saved || saved.fingerprint !== fingerprint) saved = {fingerprint,id:crypto.randomUUID()};
 sessionStorage.setItem('dosmart_draft_request',JSON.stringify(saved));
 document.querySelectorAll('.draft-button').forEach(el=>el.disabled=true);
 try {
  const response = await fetch(@json(route('pilot.create')), {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({request_id:saved.id,items})});
  const data = await response.json();
  if (!response.ok) {
   if (response.status === 410) sessionStorage.removeItem('dosmart_draft_request');
   throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Не удалось создать корзину');
  }
  saved.code=data.code; sessionStorage.setItem('dosmart_draft_request',JSON.stringify(saved));
  const localUrl = '/o/'+encodeURIComponent(data.code);
  if (!share) { window.location.href = localUrl; return; }
  document.getElementById('chat-text').value=data.chat_text;
  document.getElementById('draft-link').href=localUrl;
  document.getElementById('share-draft').style.display='block';
  document.getElementById('share-draft').scrollIntoView({behavior:'smooth'});
 } catch(error) { showToast(error.message || 'Проверьте соединение'); }
 finally { document.querySelectorAll('.draft-button').forEach(el=>el.disabled=false); }
}
async function copyDraft() {
 const field=document.getElementById('chat-text');
 try { await navigator.clipboard.writeText(field.value); showToast('Сообщение скопировано'); }
 catch { field.focus(); field.select(); showToast('Выделенный текст можно скопировать вручную'); }
}

function fmt(n) { return n.toLocaleString('ru-RU') + ' ₸'; }

function renderCart() {
    const cart = getCart();
    const tbody = document.getElementById('cart-rows');
    const empty = document.getElementById('cart-empty');
    const content = document.getElementById('cart-content');

    if (cart.length === 0) {
        empty.style.display = 'block';
        content.style.display = 'none';
        return;
    }
    empty.style.display = 'none';
    content.style.display = 'block';

    tbody.innerHTML = cart.map(item => `
        <tr id="row-${item.product_id}">
            <td style="font-weight:600">${escapeHtml(item.product_name)}</td>
            <td style="text-align:center">
                <div style="display:inline-flex;align-items:center;gap:8px">
                    <button onclick="cartDec(${item.product_id})" style="width:28px;height:28px;border:1px solid var(--border);border-radius:7px;background:var(--bg);cursor:pointer;font-size:16px;font-weight:700">−</button>
                    <span style="min-width:20px;text-align:center;font-weight:700">${item.quantity}</span>
                    <button onclick="cartInc(${item.product_id})" style="width:28px;height:28px;border:1px solid var(--border);border-radius:7px;background:var(--bg);cursor:pointer;font-size:16px;font-weight:700">+</button>
                </div>
            </td>
            <td style="text-align:right;font-weight:700">${fmt(item.total)}</td>
            <td style="text-align:right">
                <button onclick="cartRemove(${item.product_id})" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:16px">✕</button>
            </td>
        </tr>
    `).join('');

    const subtotal = cart.reduce((s, i) => s + i.total, 0);
    document.getElementById('sum-subtotal').textContent = fmt(subtotal);
    document.getElementById('sum-total').textContent = fmt(subtotal + DELIVERY_FEE);
}

function cartInc(id) { setQty(id, getQty(id) + 1); renderCart(); }
function cartDec(id) { setQty(id, getQty(id) - 1); renderCart(); }
function cartRemove(id) { removeFromCart(id); renderCart(); }

document.addEventListener('DOMContentLoaded', renderCart);
</script>
@endsection
