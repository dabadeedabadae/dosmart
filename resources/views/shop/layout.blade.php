<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'DOSMART — Интернет-магазин')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}?v=20260929">

</head>
<body>
<a class="skip-link" href="#main-content">Перейти к содержимому</a>

<header class="header">
    <div class="header-inner">
        <a href="{{ route('shop.index') }}" class="logo">
            @include('shop.partials.logo')
        </a>
        <span class="brand-caption">Для тех,<br>кто вам дорог</span>
        <div class="header-spacer"></div>
        <a class="header-catalog account-link" href="{{ route('shop.index') }}#catalog">Каталог</a>
        @auth('customer')
        <a class="account-link" href="{{ route('shop.orders') }}">Мои заказы</a>
        <form method="post" action="{{ route('shop.logout') }}">@csrf<button class="account-link account-logout">Выйти</button></form>
        @else<a class="account-link" href="{{ route('shop.login') }}">Войти</a><a class="account-link register-link" href="{{ route('shop.register') }}">Регистрация</a>@endauth
        <a href="{{ route('shop.cart') }}" class="cart-btn" id="cart-btn">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            Корзина
            <span class="cart-count" id="cart-count">0</span>
        </a>
    </div>
</header>

<main class="container page" id="main-content">
    @yield('content')
</main>

<footer class="store-footer"><div class="container">
    <div><a class="logo footer-logo" href="{{ route('shop.index') }}">@include('shop.partials.logo')</a><p>Обычные покупки. Важная забота.</p></div>
    <div class="footer-links"><a href="{{ route('shop.index') }}">Каталог товаров</a><a href="{{ route('shop.orders') }}">Мои заказы</a></div>
    <div class="footer-links"><a href="{{ route('privacy') }}">Обработка данных</a><a href="{{ route('admin.login') }}">Вход для сотрудников</a></div>
</div></footer>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
// ─── Cart store (localStorage) ───────────────────────────────────────────────
const CART_KEY = 'dosmart_cart';
function escapeHtml(value) { const el = document.createElement('span'); el.textContent = String(value); return el.innerHTML; }

function getCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; } catch { return []; }
}
function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartUI();
}
function cartTotal() { return getCart().reduce((s, i) => s + i.quantity, 0); }

function addToCart(product) {
    const cart = getCart();
    const idx  = cart.findIndex(i => i.product_id === product.product_id);
    if (idx >= 0) {
        cart[idx].quantity += 1;
        cart[idx].total     = cart[idx].quantity * cart[idx].price;
    } else {
        cart.push({ ...product, quantity: 1, total: product.price });
    }
    saveCart(cart);
    showToast('Добавлено в корзину');
}

function removeFromCart(productId) {
    saveCart(getCart().filter(i => i.product_id !== productId));
}

function setQty(productId, qty) {
    if (qty <= 0) { removeFromCart(productId); return; }
    const cart = getCart();
    const idx  = cart.findIndex(i => i.product_id === productId);
    if (idx >= 0) { cart[idx].quantity = qty; cart[idx].total = qty * cart[idx].price; }
    saveCart(cart);
}

function getQty(productId) {
    return getCart().find(i => i.product_id === productId)?.quantity || 0;
}

function updateCartUI() {
    const total = cartTotal();
    const el    = document.getElementById('cart-count');
    if (el) {
        el.textContent = total;
        el.classList.toggle('hidden', total === 0);
    }
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
}

// Init on every page
document.addEventListener('DOMContentLoaded', updateCartUI);
</script>

@yield('scripts')
</body>
</html>
