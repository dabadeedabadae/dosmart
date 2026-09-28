@extends('admin.layouts.app')
@section('title', 'Товары')

@section('content')
<div class="page-header">
    <h1 class="page-title">Товары</h1>
</div>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" style="display:contents">
            <div class="search-wrap">
                <svg class="search-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" name="q" class="form-control search-input"
                       placeholder="Поиск товара..." value="{{ request('q') }}">
            </div>
            <select name="category_id" class="form-control" style="width:180px" onchange="this.form.submit()">
                <option value="">Все категории</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
            @if(request('q'))
                <button type="submit" class="btn btn-ghost btn-sm">Найти</button>
            @endif
        </form>
    </div>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M12 5v14M5 12h14"/>
        </svg>
        Добавить товар
    </a>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Товар</th>
                    <th>Категория</th>
                    <th>Цена</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px">
                            @if($product->image_url)
                                <img src="{{ $product->image_url }}" alt=""
                                     style="width:40px;height:40px;border-radius:8px;object-fit:cover;border:1px solid var(--border)">
                            @else
                                <div class="img-placeholder">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <path d="m21 15-5-5L5 21"/>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <div style="font-weight:600;color:var(--text)">{{ $product->name }}</div>
                                @if($product->unit)
                                    <div style="font-size:11.5px;color:var(--text-muted)">{{ $product->unit }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($product->category)
                            <span class="cat-pill">{{ $product->category->name }}</span>
                        @else
                            <span style="color:var(--text-muted)">—</span>
                        @endif
                    </td>
                    <td style="font-weight:700;white-space:nowrap">
                        {{ number_format($product->price, 0, ',', ' ') }} ₸
                    </td>
                    <td style="width:80px">
                        <div style="display:flex;gap:2px;justify-content:flex-end">
                            <a href="{{ route('admin.products.edit', $product) }}" class="icon-btn" title="Редактировать">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                                  onsubmit="return confirm('Удалить «{{ $product->name }}»?')" style="display:contents">
                                @csrf @method('DELETE')
                                <button type="submit" class="icon-btn delete" title="Удалить">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                        <path d="M10 11v6M14 11v6M9 6V4h6v2"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center;padding:48px 16px;color:var(--text-muted)">
                        Товаров нет
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($products->hasPages())
        <div style="padding:12px 16px;border-top:1px solid var(--border)">
            {{ $products->withQueryString()->links('admin.pagination') }}
        </div>
    @endif
</div>
@endsection
