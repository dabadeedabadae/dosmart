@extends('admin.layouts.app')
@section('title', isset($product) ? 'Редактировать товар' : 'Новый товар')

@section('content')
<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="{{ route('admin.products.index') }}" class="icon-btn">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </a>
        <h1 class="page-title">{{ isset($product) ? 'Редактировать товар' : 'Новый товар' }}</h1>
    </div>
</div>

<div class="card form-card">
    <div class="card-body" style="padding:28px">
        <form method="POST" enctype="multipart/form-data"
              action="{{ isset($product) ? route('admin.products.update', $product) : route('admin.products.store') }}">
            @csrf
            @if(isset($product)) @method('PUT') @endif

            <div class="form-group">
                <label class="form-label">Название <span style="color:var(--danger-text)">*</span></label>
                <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-error' : '' }}"
                       value="{{ old('name', $product->name ?? '') }}" required autofocus
                       placeholder="Например: Вода питьевая 1.5л">
                @error('name') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Категория <span style="color:var(--danger-text)">*</span></label>
                <select name="category_id" class="form-control {{ $errors->has('category_id') ? 'is-error' : '' }}" required>
                    <option value="">Выберите категорию...</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}"
                            {{ old('category_id', $product->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Цена (₸) <span style="color:var(--danger-text)">*</span></label>
                    <input type="number" name="price" class="form-control {{ $errors->has('price') ? 'is-error' : '' }}"
                           value="{{ old('price', $product->price ?? '') }}" min="0" step="1" required
                           placeholder="0">
                    @error('price') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Единица измерения</label>
                    <input type="text" name="unit" class="form-control"
                           value="{{ old('unit', $product->unit ?? '') }}"
                           placeholder="1кг, 500мл, 1 шт.">
                    <div class="form-hint">Отображается под ценой в приложении</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control"
                          placeholder="Краткое описание товара (необязательно)">{{ old('description', $product->description ?? '') }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Фото товара</label>
                @if(!empty($product->image_url ?? null))
                    <div style="margin-bottom:10px">
                        <img src="{{ $product->image_url }}" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13.5px;font-weight:500;margin-bottom:10px">
                        <input type="checkbox" name="remove_photo" value="1"
                               style="width:16px;height:16px;accent-color:var(--danger-text);cursor:pointer">
                        Удалить текущее фото
                    </label>
                @endif
                <input type="file" name="photo" accept="image/*"
                       class="form-control {{ $errors->has('photo') ? 'is-error' : '' }}">
                <div class="form-hint">JPG, PNG или WebP, до 4 МБ. Оставьте пустым, чтобы не менять фото</div>
                @error('photo') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div style="display:flex;gap:20px;margin-bottom:20px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13.5px;font-weight:500">
                    <input type="hidden" name="in_stock" value="0">
                    <input type="checkbox" name="in_stock" value="1"
                           {{ old('in_stock', $product->in_stock ?? true) ? 'checked' : '' }}
                           style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer">
                    В наличии
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13.5px;font-weight:500">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}
                           style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer">
                    Активен (виден в каталоге)
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    {{ isset($product) ? 'Сохранить изменения' : 'Добавить товар' }}
                </button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-ghost">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection
