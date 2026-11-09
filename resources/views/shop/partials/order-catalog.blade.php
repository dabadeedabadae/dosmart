<details class="edit-catalog" data-catalog>
<summary class="btn btn-ghost" style="cursor:pointer;margin:20px 0">＋ Добавить товары</summary>
<p class="form-hint">Выберите товары ниже. Они попадут в заявку после подтверждения сотрудником.</p>
<label class="form-label">Поиск товаров<input class="form-control" type="search" data-catalog-search placeholder="Название товара"></label>
<label class="form-label">Категория<select class="form-control" data-catalog-category><option value="">Все категории</option>@foreach($catalogProducts->pluck('category')->filter()->unique('id') as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
<p data-catalog-count aria-live="polite"></p>
<div class="edit-products">
@foreach($catalogProducts as $product)
<article class="edit-product" data-add-product data-name="{{ $product->name }}" data-category="{{ $product->category_id }}" data-price="{{ $product->price }}" data-original="0">
<div class="edit-photo">@if($product->image_url)<img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" onerror="this.hidden=true">@else<span>Нет фото</span>@endif</div>
<div class="edit-product-info"><h3>{{ $product->name }}</h3><p>{{ number_format($product->price,0,',',' ') }} ₸ <span>/ {{ $product->unit ?: 'шт.' }}</span></p></div>
<input type="hidden" name="additions[{{ $product->id }}][product_id]" value="{{ $product->id }}" disabled>
<input type="hidden" name="additions[{{ $product->id }}][price]" value="{{ $product->price }}" disabled>
<div class="edit-product-bottom"><div class="edit-stepper">
<button type="button" data-quantity-action="minus" aria-label="Уменьшить количество: {{ $product->name }}">−</button>
<input type="number" inputmode="numeric" aria-label="Количество: {{ $product->name }}" name="additions[{{ $product->id }}][quantity]" value="{{ old('additions.'.$product->id.'.quantity',0) }}" min="0" max="1000">
<button type="button" data-quantity-action="plus" aria-label="Увеличить количество: {{ $product->name }}">+</button>
</div><strong data-line-total>0 ₸</strong></div>
<button type="button" class="edit-remove" data-quantity-action="remove">Добавить в заявку</button>
</article>
@endforeach
</div>
<nav class="edit-pages" aria-label="Страницы каталога"><button type="button" class="btn btn-ghost" data-catalog-page="-1">← Назад</button><span data-catalog-page-label></span><button type="button" class="btn btn-ghost" data-catalog-page="1">Далее →</button></nav>
</details>
<p class="form-hint" data-added-summary aria-live="polite"></p>
