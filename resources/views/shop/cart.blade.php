@extends('shop.layout')
@section('title', 'Корзина — Dosmart')
@section('body-class', 'checkout-page')
@section('content')
<div class="checkout-wrap">
    <a class="checkout-back" href="{{ route('shop.index') }}">← В каталог</a>
    <div id="cart-empty" class="checkout-empty" hidden>
        <span class="checkout-kicker">DOSMART</span><h1>В корзине пока пусто</h1>
        <p>Выберите товары для близкого. Доставка — {{ number_format((float) config('pilot.delivery_fee', 3000), 0, ',', ' ') }} ₸.</p>
        <a class="btn btn-primary btn-lg" href="{{ route('shop.index') }}">Выбрать товары</a>
    </div>
    <div id="cart-content">
        <div class="checkout-heading"><span class="checkout-kicker">ШАГ 1 ИЗ 2</span><h1>Ваша корзина</h1><p>Проверьте товары и количество. Данные получателя — на следующем шаге.</p></div>
        <div class="checkout-grid">
            <section class="checkout-panel" aria-label="Товары в корзине"><div id="cart-rows" class="cart-items"></div></section>
            <aside class="checkout-panel checkout-summary">
                <h2>Сумма заказа</h2>
                <div class="checkout-money"><span>Товары</span><strong id="sum-subtotal">0 ₸</strong></div>
                <div class="checkout-money"><span>Доставка</span><strong>{{ number_format((float) config('pilot.delivery_fee', 3000), 0, ',', ' ') }} ₸</strong></div>
                <div class="checkout-grand"><span>С доставкой</span><strong id="sum-total" aria-live="polite">0 ₸</strong></div>
                <p class="checkout-note">Предварительная сумма. На следующем шаге проверим актуальные цены.</p>
                <p id="cart-error" class="checkout-error" role="alert" hidden></p>
                <button type="button" onclick="prepareDraft()" class="btn btn-primary btn-block btn-lg draft-button">Продолжить оформление →</button>
                <p class="checkout-note">Сейчас платить не нужно.</p>
            </aside>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
const DELIVERY_FEE = @json((float) config('pilot.delivery_fee', 3000));
async function prepareDraft() {
 const items = getCart().map(i=>({product_id:Number(i.product_id),quantity:Number(i.quantity)}));
 if (!items.length) return showToast('Корзина пуста');
 const errorBox=document.getElementById('cart-error'); errorBox.hidden=true;
 const fingerprint=JSON.stringify(items);
 let saved;
 try { saved=JSON.parse(sessionStorage.getItem('dosmart_draft_request')); } catch {}
 if (!saved || saved.fingerprint!==fingerprint) saved={fingerprint,id:crypto.randomUUID()};
 sessionStorage.setItem('dosmart_draft_request',JSON.stringify(saved));
 const button=document.querySelector('.draft-button');button.disabled=true;button.textContent='Проверяем товары…';
 try {
  const response=await fetch(@json(route('pilot.create')),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({request_id:saved.id,items})});
  const data=await response.json();
  if (!response.ok) {
   if(response.status===410) sessionStorage.removeItem('dosmart_draft_request');
   throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'Не удалось проверить товары. Попробуйте ещё раз.');
  }
  saved.code=data.code;sessionStorage.setItem('dosmart_draft_request',JSON.stringify(saved));
  window.location.href='/o/'+encodeURIComponent(data.code);
 } catch(error) { errorBox.textContent=error.message||'Проверьте соединение и повторите.';errorBox.hidden=false; }
 finally {button.disabled=false;button.textContent='Продолжить оформление →';}
}
function fmt(n) {return n.toLocaleString('ru-RU',{maximumFractionDigits:2})+' ₸';}
function renderCart() {
 const cart=getCart(),rows=document.getElementById('cart-rows');
 document.getElementById('cart-empty').hidden=cart.length!==0;
 document.getElementById('cart-content').hidden=cart.length===0;
 rows.replaceChildren();let subtotalMinor=0;
 cart.forEach(item=>{
  const id=Number(item.product_id),quantity=Number(item.quantity),price=Number(item.price);
  if(!Number.isSafeInteger(id)||id<1||!Number.isInteger(quantity)||quantity<1||quantity>1000||!Number.isFinite(price)||price<0) return;
  const total=Math.round(price*100)*quantity;subtotalMinor+=total;
  const row=document.createElement('article');row.className='cart-item';
  const info=document.createElement('div');info.className='cart-item-info';
  const name=document.createElement('h2');name.textContent=item.product_name;
  const unit=document.createElement('p');unit.textContent=fmt(price)+' за единицу';info.append(name,unit);
  const controls=document.createElement('div');controls.className='cart-item-controls';
  const stepper=document.createElement('div');stepper.className='cart-stepper';
  const minus=document.createElement('button');minus.type='button';minus.textContent='−';minus.setAttribute('aria-label','Уменьшить количество: '+item.product_name);minus.onclick=()=>{setQty(id,quantity-1);renderCart();};
  const count=document.createElement('span');count.textContent=quantity;
  const plus=document.createElement('button');plus.type='button';plus.textContent='+';plus.disabled=quantity>=1000;plus.setAttribute('aria-label','Увеличить количество: '+item.product_name);plus.onclick=()=>{setQty(id,quantity+1);renderCart();};stepper.append(minus,count,plus);
  const amount=document.createElement('strong');amount.className='cart-line-total';amount.textContent=fmt(total/100);
  const remove=document.createElement('button');remove.type='button';remove.className='cart-remove';remove.textContent='Убрать';remove.setAttribute('aria-label','Убрать из корзины: '+item.product_name);remove.onclick=()=>{removeFromCart(id);renderCart();};
  controls.append(stepper,amount,remove);row.append(info,controls);rows.append(row);
 });
 document.getElementById('sum-subtotal').textContent=fmt(subtotalMinor/100);
 document.getElementById('sum-total').textContent=fmt(subtotalMinor/100+DELIVERY_FEE);
}
document.addEventListener('DOMContentLoaded',renderCart);
window.addEventListener('storage',event=>{if(event.key===CART_KEY)renderCart();});
</script>
@endsection
