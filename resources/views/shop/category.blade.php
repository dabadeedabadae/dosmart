@extends('shop.layout')
@section('title', $category->name . ' — DOSMART')

@section('content')
<div class="shop-layout">

    <aside class="sidebar">
        <div class="sidebar-title">Категории</div>
        <a href="{{ route('shop.index') }}">
            <span class="sidebar-dot"></span> Все товары
        </a>
        @foreach($categories as $cat)
        <a href="{{ route('shop.category', $cat) }}"
           class="{{ $cat->id === $category->id ? 'active' : '' }}">
            <span class="sidebar-dot" style="background:{{ $cat->color ?? 'var(--accent)' }}"></span>
            {{ $cat->name }}
        </a>
        @endforeach
    </aside>

    <div>
        <div class="breadcrumb">
            <a href="{{ route('shop.index') }}">Каталог</a>
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="m9 18 6-6-6-6"/>
            </svg>
            {{ $category->name }}
        </div>

        <div class="section-title">{{ $category->name }}</div>

        @if($products->isNotEmpty())
        <div class="products-grid">
            @foreach($products as $product)
                @include('shop.partials.product-card', ['product' => $product])
            @endforeach
        </div>
        @else
        <div class="empty">
            <div class="empty-icon">📦</div>
            <h3>Товаров нет</h3>
            <p>В этой категории пока нет товаров в наличии</p>
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
            В корзину</button>`;
    } else {
        foot.innerHTML = `<div class="product-counter">
            <button aria-label="Уменьшить количество" onclick="handleDec(this, ${productId})">−</button>
            <span class="qty">${qty}</span>
            <button aria-label="Увеличить количество" onclick="handleInc(this, ${productId})">+</button>
        </div>`;
    }
}

function handleAdd(btn, productId) {
    const card = btn.closest('[data-product-id]');
    addToCart(JSON.parse(card.dataset.product));
    renderProductActions(card, productId);
}
function handleInc(btn, productId) {
    setQty(productId, getQty(productId) + 1);
    renderProductActions(btn.closest('[data-product-id]'), productId);
}
function handleDec(btn, productId) {
    setQty(productId, getQty(productId) - 1);
    renderProductActions(btn.closest('[data-product-id]'), productId);
}
</script>
@endsection
