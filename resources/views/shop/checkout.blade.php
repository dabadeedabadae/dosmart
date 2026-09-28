@extends('shop.layout')
@section('title', 'Оформление заказа — DOSMART')

@section('content')
<div style="max-width:680px;margin:0 auto">

    <div class="breadcrumb">
        <a href="{{ route('shop.index') }}">Каталог</a>
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="m9 18 6-6-6-6"/>
        </svg>
        <a href="{{ route('shop.cart') }}">Корзина</a>
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="m9 18 6-6-6-6"/>
        </svg>
        Оформление
    </div>

    <div id="empty-redirect" style="display:none" class="empty">
        <div class="empty-icon">🛒</div>
        <h3>Корзина пуста</h3>
        <a href="{{ route('shop.index') }}" class="btn btn-primary" style="margin-top:16px;display:inline-flex">
            В каталог
        </a>
    </div>

    <div id="checkout-form">
        <div class="section-title">Оформление заказа</div>

        <div style="display:flex;flex-direction:column;gap:20px">

            {{-- Информация о получателе --}}
            <div class="form-card">
                <div style="font-size:15px;font-weight:800;margin-bottom:18px">Информация о получателе</div>

                <div class="form-group">
                    <label class="form-label" for="prisoner_name">
                        ФИО осуждённого <span class="req">*</span>
                    </label>
                    <input type="text" id="prisoner_name" class="form-control"
                           placeholder="Иванов Иван Иванович" required>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="form-group">
                        <label class="form-label" for="squad_number">Номер отряда</label>
                        <input type="text" id="squad_number" class="form-control" placeholder="Например: 3">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="institution_name">
                            Учреждение <span class="req">*</span>
                        </label>
                        <select id="institution_name" class="form-control" required>
                            <option value="">— выберите учреждение —</option>
                            @foreach($institutions as $inst)
                            <option value="{{ $inst->name }}">{{ $inst->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Контакт для связи --}}
            <div class="form-card">
                <div style="font-size:15px;font-weight:800;margin-bottom:6px">Контакт для связи</div>
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px">
                    Оператор свяжется с этим человеком для подтверждения и оплаты заказа
                </p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="form-group">
                        <label class="form-label" for="relative_name">Имя родственника</label>
                        <input type="text" id="relative_name" class="form-control" placeholder="Иванова Мария">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_phone">
                            Телефон <span class="req">*</span>
                        </label>
                        <input type="tel" id="contact_phone" class="form-control"
                               placeholder="+7 (700) 000-00-00" value="{{ auth('customer')->user()->phone }}" required>
                        <div class="form-hint">На этот номер позвонит оператор</div>
                    </div>
                </div>
            </div>

            {{-- Состав заказа --}}
            <div class="form-card" style="padding:0;overflow:hidden">
                <div style="padding:16px 20px;font-size:15px;font-weight:800;border-bottom:1px solid var(--border)">
                    Ваш заказ
                </div>
                <div id="order-items"></div>
                <div style="padding:16px 20px;border-top:1px solid var(--border)">
                    <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary);margin-bottom:6px">
                        <span>Товары</span><span id="subtotal-text">0 ₸</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary);margin-bottom:10px">
                        <span>Доставка</span><span>3 000 ₸</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;border-top:1px solid var(--border);padding-top:10px">
                        <span>Итого</span><span id="total-text">0 ₸</span>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:12px">
                <a href="{{ route('shop.cart') }}" class="btn btn-ghost">← Назад</a>
                <button type="button" onclick="submitOrder()" class="btn btn-primary btn-lg" style="flex:1;justify-content:center" id="submit-btn">
                    Подтвердить заказ
                </button>
            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const DELIVERY_FEE = 3000;
const API_URL = '{{ route('shop.orders.store') }}';
const SUCCESS_URL = '{{ route("shop.success") }}';

function fmt(n) { return n.toLocaleString('ru-RU') + ' ₸'; }

function renderOrderItems() {
    const cart = getCart();
    if (cart.length === 0) {
        document.getElementById('checkout-form').style.display = 'none';
        document.getElementById('empty-redirect').style.display = 'block';
        return;
    }

    const subtotal = cart.reduce((s, i) => s + i.total, 0);
    document.getElementById('subtotal-text').textContent = fmt(subtotal);
    document.getElementById('total-text').textContent = fmt(subtotal + DELIVERY_FEE);

    document.getElementById('order-items').innerHTML = cart.map(item => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid var(--border)">
            <div>
                <div style="font-weight:600;font-size:13.5px">${escapeHtml(item.product_name)}</div>
                <div style="font-size:12px;color:var(--text-muted)">${item.quantity} × ${fmt(item.price)}</div>
            </div>
            <div style="font-weight:700">${fmt(item.total)}</div>
        </div>
    `).join('');
}

async function submitOrder() {
    const cart = getCart();
    if (cart.length === 0) { showToast('Корзина пуста'); return; }

    const prisoner_name    = document.getElementById('prisoner_name').value.trim();
    const squad_number     = document.getElementById('squad_number').value.trim();
    const institution_name = document.getElementById('institution_name').value;
    const contact_phone    = document.getElementById('contact_phone').value.trim();
    const relative_name    = document.getElementById('relative_name').value.trim();

    if (!prisoner_name)    { showToast('Введите ФИО осуждённого'); return; }
    if (!institution_name) { showToast('Выберите учреждение'); return; }
    if (!contact_phone)    { showToast('Введите контактный телефон'); return; }

    const subtotal = cart.reduce((s, i) => s + i.total, 0);
    const total    = subtotal + DELIVERY_FEE;

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Отправляем...';

    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                prisoner_name,
                squad_number:     squad_number || null,
                institution_name,
                contact_phone,
                relative_name:    relative_name || null,
                items:       cart,
                subtotal,
                delivery_fee: DELIVERY_FEE,
                total,
            }),
        });

        if (res.status === 401 || res.status === 419) { window.location.href = '{{ route('shop.login') }}'; return; }
        const data = await res.json();

        if (!res.ok) {
            const msg = data.message || data.error || 'Ошибка при отправке заказа';
            showToast(msg);
            btn.disabled = false;
            btn.textContent = 'Подтвердить заказ';
            return;
        }

        // Сохраняем номер заказа и очищаем корзину
        localStorage.setItem('dosmart_last_order', data.order_number);
        saveCart([]);
        window.location.href = SUCCESS_URL + '?order=' + data.order_number;

    } catch (e) {
        showToast('Нет соединения. Проверьте интернет.');
        btn.disabled = false;
        btn.textContent = 'Подтвердить заказ';
    }
}

document.addEventListener('DOMContentLoaded', renderOrderItems);
</script>
@endsection
