@extends('admin.layouts.app')
@section('title', isset($category) ? 'Редактировать категорию' : 'Новая категория')

@section('content')
<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="{{ route('admin.categories.index') }}" class="icon-btn">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </a>
        <h1 class="page-title">{{ isset($category) ? 'Редактировать категорию' : 'Новая категория' }}</h1>
    </div>
</div>

<div class="card form-card">
    <div class="card-body" style="padding:28px">
        <form method="POST"
              action="{{ isset($category) ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
            @csrf
            @if(isset($category)) @method('PUT') @endif

            <div class="form-group">
                <label class="form-label">Название (рус.) <span style="color:var(--danger-text)">*</span></label>
                <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-error' : '' }}"
                       value="{{ old('name', $category->name ?? '') }}" required autofocus
                       placeholder="Например: Напитки">
                @error('name') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Название (каз.)</label>
                    <input type="text" name="name_kk" class="form-control"
                           value="{{ old('name_kk', $category->name_kk ?? '') }}"
                           placeholder="Сусындар">
                </div>
                <div class="form-group">
                    <label class="form-label">Иконка</label>
                    <input type="text" name="icon" class="form-control"
                           value="{{ old('icon', $category->icon ?? '') }}"
                           placeholder="water_drop">
                    <div class="form-hint">Название Material icon</div>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Цвет</label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input type="color" name="color"
                               value="{{ old('color', $category->color ?? '#2196F3') }}"
                               style="width:44px;height:38px;border:1px solid var(--border);border-radius:8px;padding:3px;cursor:pointer;background:var(--surface)">
                        <input type="text" name="color_text" class="form-control"
                               value="{{ old('color', $category->color ?? '#2196F3') }}"
                               placeholder="#2196F3" style="flex:1"
                               oninput="this.previousElementSibling.value=this.value">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Порядок сортировки</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0">
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13.5px;font-weight:500;margin-bottom:22px">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}
                       style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer">
                Активна (видна в каталоге)
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    {{ isset($category) ? 'Сохранить' : 'Добавить категорию' }}
                </button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection
