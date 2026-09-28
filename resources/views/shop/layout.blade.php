<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'DOSMART — Интернет-магазин')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: oklch(97.3% 0.006 255);
            --surface: oklch(100% 0 0);
            --border: oklch(90% 0.007 255);
            --text: oklch(23% 0.02 255);
            --text-secondary: oklch(48% 0.015 255);
            --text-muted: oklch(63% 0.012 255);
            --accent: oklch(53% 0.17 258);
            --accent-hover: oklch(46% 0.17 258);
            --accent-light: oklch(94% 0.035 258);
            --success: oklch(38% 0.12 150);
            --success-bg: oklch(94% 0.05 150);
            --danger: oklch(42% 0.16 25);
            --danger-bg: oklch(95% 0.045 25);
            --header-h: 64px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* Header */
        .header {
            position: sticky; top: 0; z-index: 100;
            height: var(--header-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
        }
        .header-inner {
            width: 100%; max-width: 1100px; margin: 0 auto;
            padding: 0 20px;
            display: flex; align-items: center; gap: 20px;
        }
        .logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; color: var(--text);
            flex-shrink: 0;
        }
        .logo-icon {
            width: 36px; height: 36px;
            background: var(--accent);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 18px; font-weight: 800;
        }
        .logo-text { font-size: 15px; font-weight: 800; letter-spacing: .04em; }
        .header-spacer { flex: 1; }

        /* Cart button */
        .cart-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 16px;
            background: var(--accent);
            color: #fff;
            border: none; border-radius: 10px;
            font-family: inherit; font-size: 14px; font-weight: 700;
            cursor: pointer; text-decoration: none;
            transition: background .1s;
            position: relative;
        }
        .cart-btn:hover { background: var(--accent-hover); }
        .cart-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; padding: 0 5px;
            background: rgba(255,255,255,.25);
            border-radius: 10px;
            font-size: 12px; font-weight: 800;
        }
        .cart-count.hidden { display: none; }

        /* Layout */
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        .page { padding: 24px 0 60px; }

        /* Category sidebar */
        .shop-layout {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 700px) {
            .shop-layout { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
        .sidebar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            position: sticky; top: calc(var(--header-h) + 16px);
        }
        .sidebar-title {
            padding: 14px 16px;
            font-size: 12px; font-weight: 700;
            color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em;
            border-bottom: 1px solid var(--border);
        }
        .sidebar a {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 16px;
            text-decoration: none; color: var(--text-secondary);
            font-size: 13.5px; font-weight: 500;
            border-bottom: 1px solid var(--border);
            transition: background .1s, color .1s;
        }
        .sidebar a:last-child { border-bottom: none; }
        .sidebar a:hover, .sidebar a.active {
            background: var(--accent-light); color: var(--accent);
        }
        .sidebar-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: currentColor; flex-shrink: 0;
        }

        /* Product grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 14px;
        }
        .product-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            cursor: pointer;
            transition: box-shadow .15s, transform .15s;
            display: flex; flex-direction: column;
        }
        .product-card:hover {
            box-shadow: 0 4px 20px rgb(0 0 0 / 8%);
            transform: translateY(-2px);
        }
        .product-img {
            width: 100%; aspect-ratio: 1;
            object-fit: cover;
            background: var(--bg);
            display: flex; align-items: center; justify-content: center;
            font-size: 36px;
        }
        .product-img-placeholder {
            width: 100%; aspect-ratio: 1;
            background: var(--bg);
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; color: var(--text-muted);
        }
        .product-body { padding: 12px 14px 14px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .product-name { font-size: 13.5px; font-weight: 600; line-height: 1.35; }
        .product-price { font-size: 15px; font-weight: 800; color: var(--accent); margin-top: auto; padding-top: 8px; }
        .product-weight { font-size: 11.5px; color: var(--text-muted); }
        .product-add {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            width: 100%;
            padding: 9px 12px;
            background: var(--accent-light); color: var(--accent);
            border: none; border-radius: 0 0 14px 14px;
            font-family: inherit; font-size: 13px; font-weight: 700;
            cursor: pointer;
            transition: background .1s;
        }
        .product-add:hover { background: var(--accent); color: #fff; }
        .product-add.in-cart { background: var(--success-bg); color: var(--success); }
        .product-counter {
            display: flex; align-items: center; gap: 0;
            border-radius: 0 0 14px 14px; overflow: hidden;
        }
        .product-counter button {
            flex: 1;
            padding: 9px 0;
            background: var(--accent-light); color: var(--accent);
            border: none; font-family: inherit; font-size: 16px; font-weight: 700;
            cursor: pointer; transition: background .1s;
        }
        .product-counter button:hover { background: var(--accent); color: #fff; }
        .product-counter .qty {
            flex: 1; text-align: center;
            font-size: 14px; font-weight: 700; color: var(--text);
            background: var(--bg); pointer-events: none;
            padding: 9px 0;
        }

        /* Section header */
        .section-title {
            font-size: 17px; font-weight: 800; margin-bottom: 16px;
        }
        .breadcrumb {
            display: flex; align-items: center; gap: 6px;
            font-size: 13px; color: var(--text-muted);
            margin-bottom: 18px;
        }
        .breadcrumb a { color: var(--accent); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* Cart page */
        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th, .cart-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 13.5px;
        }
        .cart-table th { font-weight: 700; font-size: 12px; color: var(--text-muted); }
        .cart-table td:last-child { text-align: right; }
        .cart-table th:last-child { text-align: right; }

        /* Forms */
        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
        }
        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block; font-size: 13px; font-weight: 600;
            color: var(--text-secondary); margin-bottom: 5px;
        }
        .form-label .req { color: var(--danger); }
        .form-control {
            display: block; width: 100%;
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface);
            color: var(--text);
            font-family: inherit; font-size: 14px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-light); }
        .form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px;
            border: none; border-radius: 10px;
            font-family: inherit; font-size: 14px; font-weight: 700;
            cursor: pointer; text-decoration: none;
            transition: background .1s;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-ghost { background: var(--bg); color: var(--text-secondary); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--border); }
        .btn-lg { padding: 13px 28px; font-size: 15px; border-radius: 12px; }
        .btn-block { width: 100%; justify-content: center; }

        /* Empty state */
        .empty {
            text-align: center; padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }
        .empty h3 { font-size: 16px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px; }
        .empty p { font-size: 13.5px; }

        /* Toast */
        .toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 999;
            background: var(--text); color: #fff;
            padding: 12px 18px; border-radius: 12px;
            font-size: 14px; font-weight: 600;
            box-shadow: 0 4px 20px rgb(0 0 0 / 15%);
            transform: translateY(80px); opacity: 0;
            transition: all .3s;
            pointer-events: none;
        }
        .toast.show { transform: translateY(0); opacity: 1; }

        /* Summary block */
        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            position: sticky; top: calc(var(--header-h) + 16px);
        }
        .summary-row {
            display: flex; justify-content: space-between;
            font-size: 13.5px; padding: 6px 0;
            border-bottom: 1px solid var(--border);
        }
        .summary-row:last-of-type { border-bottom: none; }
        .summary-total {
            display: flex; justify-content: space-between;
            font-size: 16px; font-weight: 800;
            padding-top: 12px; margin-top: 4px;
            border-top: 2px solid var(--border);
        }
        :root { --bg:#f8f9f6; --text:#183c32; --accent:#24745c; --accent-hover:#185640; --accent-light:#e6f1e9; --border:#dfe6de; }
        a { color:var(--accent); } button:disabled { opacity:.55; cursor:wait; }
        :focus-visible { outline:3px solid #cc9e24; outline-offset:3px; }
        .header-inner,.container { max-width:1240px; }
        .container.page { padding:24px 20px 60px; }
        .shop-layout > * { min-width:0; }
        .sidebar { max-width:100%; }
        .account-link { font-size:13px; font-weight:700; text-decoration:none; white-space:nowrap; }
        .account-logout { border:0; background:none; color:var(--text-secondary); cursor:pointer; }
        .hero { display:grid; grid-template-columns:1.4fr 1fr; background:#e9efe2; border-radius:24px; padding:48px; overflow:hidden; gap:24px; }
        .eyebrow { font-size:11px; font-weight:800; letter-spacing:.15em; margin-bottom:18px; color:var(--accent); }
        h1 { font-size:clamp(30px,4vw,52px); line-height:1.12; letter-spacing:-.035em; }
        .hero p,.intro { font-size:14px; line-height:1.8; color:var(--text-secondary); margin:18px 0 24px; }
        .hero-art { position:relative; display:flex; justify-content:center; align-items:center; min-height:250px; }
        .art-circle { position:absolute; width:270px; height:270px; background:#d2ddbf; border-radius:50%; }
        .shopping-bag { position:relative; width:180px; height:195px; background:#f5ce64; transform:rotate(-8deg); border-radius:6px 6px 18px 18px; box-shadow:15px 20px 0 #183c3212; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .bag-handle { position:absolute; width:70px; height:65px; border:9px solid #be963c; border-bottom:0; border-radius:40px 40px 0 0; top:-44px; }
        .shopping-bag strong { font-size:80px; line-height:1; }.shopping-bag strong span { font-size:25px; }.shopping-bag small { letter-spacing:.18em; font-weight:800; }
        .hero-note { position:absolute; bottom:0; right:0; background:white; padding:14px 20px; border-radius:12px; font-size:13px; transform:rotate(5deg); }
        .benefits { display:flex; justify-content:space-between; gap:16px; padding:24px 8px; font-size:12px; color:var(--text-secondary); border-bottom:1px solid var(--border); margin-bottom:28px; }
        .catalog-search { display:flex; gap:10px; margin-bottom:24px; scroll-margin-top:90px; }.catalog-search select { max-width:210px; }
        .product-img { object-fit:contain; padding:16px; background:#f1f3ee; }.product-img-placeholder { background:#f1f3ee; color:#8b9f90; }
        .product-body { padding:18px; }.product-price { color:var(--text); font-size:19px; }.product-name { font-size:14px; }
        .unavailable { padding:12px; text-align:center; font-size:13px; color:var(--text-muted); background:var(--bg); }
        .auth-layout { display:grid; grid-template-columns:1fr 1fr; gap:70px; max-width:950px; margin:60px auto; align-items:center; }
        .form-errors { background:var(--danger-bg); color:var(--danger); padding:14px; border-radius:10px; margin:16px 0; font-size:13px; }
        .order-heading { display:flex; justify-content:space-between; gap:15px; align-items:center; margin-bottom:16px; }.order-heading .section-title { margin:0; }
        .status-pill { background:var(--accent-light); color:var(--accent); padding:8px 12px; border-radius:30px; font-size:12px; }
        .pagination { display:flex; justify-content:center; align-items:center; gap:20px; margin:28px 0; font-size:13px; }
        .store-footer { padding:30px 0; border-top:1px solid var(--border); font-size:12px; color:var(--text-secondary); }.store-footer .container { display:flex; justify-content:space-between; gap:15px; }
        .sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
        @media(max-width:700px) {
            .header-inner { gap:12px; padding:0 14px; }.logo-text { font-size:12px; }.logo-icon { width:28px; height:28px; }.cart-btn { padding:9px; font-size:12px; }.register-link { display:none; }
            .hero { padding:28px; grid-template-columns:1fr; }.hero-art { min-height:220px; }.art-circle { width:215px; height:215px; }.shopping-bag { width:140px; height:150px; }.shopping-bag strong { font-size:60px; }
            .benefits { flex-direction:column; gap:12px; }.catalog-search { flex-wrap:wrap; }.catalog-search input { flex-basis:100%; }.catalog-search select { flex:1; max-width:none; }
            .sidebar { display:flex; position:static; overflow-x:auto; }.sidebar-title { display:none; }.sidebar a { white-space:nowrap; border-bottom:0; }.shop-layout { gap:20px; }
            .products-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }.product-body { padding:12px; }.product-name { font-size:13px; }
            .auth-layout { grid-template-columns:1fr; gap:28px; margin:16px 0; }.auth-layout h1 { font-size:30px; }
            .cart-columns { grid-template-columns:1fr !important; }.cart-table th,.cart-table td { padding:10px 6px; font-size:12px; }.store-footer .container { flex-wrap:wrap; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-inner">
        <a href="{{ route('shop.index') }}" class="logo">
            <div class="logo-icon">D</div>
            <span class="logo-text">DOSMART</span>
        </a>
        <div class="header-spacer"></div>
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

<div class="container page">
    @yield('content')
</div>

<footer class="store-footer"><div class="container"><strong>DOSMART</strong><span>С заботой о ваших близких</span><a href="{{ route('shop.index') }}">Каталог товаров</a><a href="{{ route('admin.login') }}">Вход для сотрудников</a></div></footer>
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
