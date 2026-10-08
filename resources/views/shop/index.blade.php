@extends('shop.layout')
@section('title', 'DOSMART — Каталог')

@section('content')
<section class="hero">
    <div class="hero-copy">
        <div class="eyebrow"><span></span> ДОСТАВКА В УЧРЕЖДЕНИЯ</div>
        <h1>Быть рядом —<br>даже на расстоянии.</h1>
        <p>Соберите для близкого продукты и нужные вещи.<br class="desktop-break"> Мы поможем с оформлением и доставкой.</p>
        <a href="#catalog" class="btn btn-primary btn-lg">Собрать корзину <span aria-hidden="true">→</span></a>
        <div class="hero-detail">Продукты, средства гигиены и повседневные мелочи</div>
    </div>
    <div class="hero-visual"><img src="{{ asset('images/grocery-bag.svg') }}" alt="Бумажная сумка с хлебом, молоком и чаем" width="540" height="440"><span class="parcel-note">В каждой посылке —<br><em>немного дома.</em></span></div>
</section>
<section class="order-shortcut" aria-labelledby="code-title">
    <div class="shortcut-heading"><span class="shortcut-icon" aria-hidden="true">↗</span><div><h2 id="code-title">Уже получили код корзины?</h2><p>Введите его здесь — все выбранные товары уже внутри.</p></div></div>
    <form method="post" action="{{ route('pilot.lookup') }}">@csrf
        <label class="sr-only" for="order-code">Код корзины</label>
        <div class="code-fields"><input class="form-control" id="order-code" name="code" placeholder="Код из сообщения" maxlength="8" required autocapitalize="characters" spellcheck="false" value="{{ old('code') }}"><button class="btn btn-primary">Открыть <span aria-hidden="true">→</span></button></div>
        @error('code')<p class="code-error" role="alert">{{ $message }}</p>@enderror
    </form>
</section>
<div class="catalog-heading" id="catalog"><div><div class="eyebrow">ПРОСТЫЕ ВЕЩИ, КОТОРЫЕ НУЖНЫ</div><h2>Что передадим близкому?</h2></div><span class="catalog-count">В каталоге: {{ $featured->total() }}</span></div>
<form class="catalog-search" action="{{ route('shop.index') }}#catalog">
    <div class="search-field"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><label class="sr-only" for="search">Поиск товаров</label><input id="search" name="q" class="form-control" placeholder="Например, чай или мыло" value="{{ request('q') }}"></div>
    <label class="sr-only" for="sort">Сортировка</label><select id="sort" name="sort" class="form-control"><option value="new">Сначала новые</option><option value="price_asc" @selected(request('sort') === 'price_asc')>Сначала дешевле</option><option value="price_desc" @selected(request('sort') === 'price_desc')>Сначала дороже</option></select><button class="btn btn-primary">Найти</button>
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
        <div class="order-heading"><h2 class="section-title">{{ request('q') ? 'Результаты поиска' : 'Все товары' }}</h2><span class="form-hint">Товаров: {{ $featured->total() }}</span></div>
        <div class="products-grid">
            @foreach($featured as $product)
                @include('shop.partials.product-card', ['product' => $product])
            @endforeach
        </div>
        @include('shop.partials.pagination', ['paginator' => $featured])
        @else
        <div class="empty">
            <div class="empty-icon" aria-hidden="true">⌕</div>
            <h3>{{ request('q') ? 'Ничего не найдено' : 'Товары скоро появятся' }}</h3>
            <p>Попробуйте другой запрос или вернитесь позже</p>
        </div>
        @endif
    </div>

</div>
<section class="how-it-works" aria-label="Как сделать заказ"><div><span>01</span><h3>Выберите нужное</h3><p>Добавьте товары в корзину или откройте её по коду от близкого.</p></div><div><span>02</span><h3>Укажите получателя</h3><p>Заполните данные и выберите доставку при оформлении.</p></div><div><span>03</span><h3>Оставайтесь на связи</h3><p>Сотрудник проверит оплату и поможет с дальнейшими шагами.</p></div></section>

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
            <button aria-label="Уменьшить количество" onclick="handleDec(this, ${productId})">−</button>
            <span class="qty">${qty}</span>
            <button aria-label="Увеличить количество" onclick="handleInc(this, ${productId})">+</button>
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
