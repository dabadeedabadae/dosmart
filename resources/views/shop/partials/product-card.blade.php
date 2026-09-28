@php
$productData = json_encode([
    'product_id'   => $product->id,
    'product_name' => $product->name,
    'price'        => (float) $product->price,
]);
@endphp
<div class="product-card" data-product-id="{{ $product->id }}" data-product="{{ $productData }}">
    @if($product->image_url)
    <img class="product-img" src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
    @else<div class="product-img-placeholder" aria-label="Без фотографии"><svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 64 64"><path d="M14 22h36v32H14z M24 22v-6a8 8 0 0 1 16 0v6 M24 34h16"/></svg></div>@endif
    <div class="product-body">
        <div class="product-name">{{ $product->name }}</div>
        @if($product->unit)
        <div class="product-weight">{{ $product->unit }}</div>
        @endif
        <div class="product-price">{{ number_format($product->price, 0, ',', ' ') }} ₸</div>
    </div>
    @if($product->in_stock)
    <div class="product-footer">
        <button class="product-add" onclick="handleAdd(this, {{ $product->id }})">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            В корзину
        </button>
    </div>
    @else<div class="unavailable">Нет в наличии</div>@endif
</div>
