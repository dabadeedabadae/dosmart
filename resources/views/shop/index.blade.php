@extends('shop.layout')
@section('title', 'DOSMART — Каталог')

@section('content')
<section class="hero"><div><div class="eyebrow">DOSMART · МАГАЗИН С ЗАБОТОЙ</div><h1>Всё нужное.<br>Для ваших близких.</h1><p>Продукты и повседневные товары с доставкой в учреждение.<br>Вы выбираете — мы собираем и передаём.</p><a href="#catalog" class="btn btn-primary btn-lg">Выбрать товары ↗</a></div><div class="hero-art" aria-hidden="true"><span class="art-circle"></span><div class="shopping-bag"><span class="bag-handle"></span><strong>D<span>●</span></strong><small>DOSMART</small></div><div class="hero-note">Собираем с заботой ♡</div></div></section>
<div class="benefits"><span>01 &nbsp; Выберите товары</span><span>02 &nbsp; Укажите получателя</span><span>03 &nbsp; Дождитесь подтверждения</span></div>
<form class="catalog-search" action="{{ route('shop.index') }}" id="catalog"><label class="sr-only" for="search">Поиск товаров</label><input id="search" name="q" class="form-control" placeholder="Найти нужный товар…" value="{{ request('q') }}"><label class="sr-only" for="sort">Сортировка</label><select id="sort" name="sort" class="form-control"><option value="new">Сначала новые</option><option value="price_asc" @selected(request('sort') === 'price_asc')>Сначала дешевле</option><option value="price_desc" @selected(request('sort') === 'price_desc')>Сначала дороже</option></select><button class="btn btn-primary">Найти</button></form>
<form method="post" action="{{ route('pilot.lookup') }}" class="form-card" style="margin-bottom:24px">@csrf
<label for="order-code" class="form-label">Получили код от близкого? Откройте готовую корзину</label>
<div style="display:flex;gap:10px"><input class="form-control" id="order-code" name="code" placeholder="Например, 7K3M9A2B" maxlength="8" required style="text-transform:uppercase"><button class="btn btn-primary">Открыть</button></div>
@error('code')<p role="alert">{{ $message }}</p>@enderror
</form>
<div class="shop-layout">

    {{-- Sidebar: категории --}}
    <aside class="sidebar">
        <div class="sidebar-title">Категории</div>
        <a href="{{ route('shop.index') }}" class="active">
            <span class="sidebar-dot"></span> Все товары
        </a>
        @foreach($categories as $cat)
        <a href="{{ route('shop.category', $cat) }}">
            <span class="sidebar-dot" style="background:{{ $cat->color ?? 'var(--accent)' }}"></span>
            {{ $cat->name }}
        </a>
        @endforeach
    </aside>

    {{-- Main content --}}
    <div>
        @if($featured->isNotEmpty())
        <div class="order-heading"><h2 class="section-title">Все товары</h2><span class="form-hint">{{ $featured->total() }} товаров</span></div>
        <div class="products-grid">
            @foreach($featured as $product)
                @include('shop.partials.product-card', ['product' => $product])
            @endforeach
        </div>
        @include('shop.partials.pagination', ['paginator' => $featured])
        @else
        <div class="empty">
            <div class="empty-icon">🏪</div>
            <h3>{{ request('q') ? 'Ничего не найдено' : 'Товары скоро появятся' }}</h3>
            <p>Попробуйте другой запрос или вернитесь позже</p>
        </div>
        @endif
    </div>

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-product-id]').forEach(el => {
        const id = parseInt(el.dataset.productId);
        renderProductActions(el, id);
    });
});

function renderProductActions(wrapper, productId) {
    const qty  = getQty(productId);
    const foot = wrapper.querySelector('.product-footer');
    if (!foot) return;

    if (qty === 0) {
        foot.innerHTML = `<button class="product-add" onclick="handleAdd(this, ${productId})">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            В корзину
        </button>`;
    } else {
        foot.innerHTML = `<div class="product-counter">
            <button onclick="handleDec(this, ${productId})">−</button>
            <span class="qty">${qty}</span>
            <button onclick="handleInc(this, ${productId})">+</button>
        </div>`;
    }
}

function handleAdd(btn, productId) {
    const card = btn.closest('[data-product-id]');
    const data = JSON.parse(card.dataset.product);
    addToCart(data);
    renderProductActions(card, productId);
}
function handleInc(btn, productId) {
    setQty(productId, getQty(productId) + 1);
    const card = btn.closest('[data-product-id]');
    renderProductActions(card, productId);
}
function handleDec(btn, productId) {
    setQty(productId, getQty(productId) - 1);
    const card = btn.closest('[data-product-id]');
    renderProductActions(card, productId);
}
</script>
@endsection
