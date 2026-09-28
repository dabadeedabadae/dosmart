@extends('admin.layouts.app')
@section('title', 'Категории')

@section('content')
<div class="page-header">
    <h1 class="page-title">Категории</h1>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M12 5v14M5 12h14"/>
        </svg>
        Добавить категорию
    </a>
</div>

@php
    $editing = request('edit') ? $categories->firstWhere('id', (int) request('edit')) : null;
@endphp

<div style="display:flex;gap:24px;align-items:flex-start">
    <div style="flex:0 0 auto;width:640px;max-width:100%">
        @if($categories->isEmpty())
            <div class="card" style="padding:48px;text-align:center;color:var(--text-muted)">
                Категорий нет. Добавьте первую.
            </div>
        @else
            <div class="card">
                @foreach($categories as $category)
                <div class="cat-list-item">
                    <div class="cat-dot {{ $category->products_count === 0 ? 'empty' : '' }}"></div>
                    <div style="flex:1;min-width:0">
                        <div class="cat-list-name">{{ $category->name }}</div>
                        <div class="cat-list-count">
                            {{ $category->products_count }}
                            @php
                                $n = $category->products_count;
                                echo $n === 1 ? 'товар' : ($n >= 2 && $n <= 4 ? 'товара' : 'товаров');
                            @endphp
                        </div>
                    </div>
                    <div class="cat-list-actions">
                        <a href="{{ route('admin.categories.index', ['edit' => $category->id]) }}"
                           class="icon-btn {{ $editing && $editing->id === $category->id ? 'active' : '' }}" title="Редактировать">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                              onsubmit="return confirm('Удалить категорию «{{ $category->name }}»?\nТовары в ней останутся без категории.')"
                              style="display:contents">
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
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div style="flex:1;min-width:320px">
        @if($editing)
            <div class="card form-card">
                <div class="card-body" style="padding:28px">
                    <h2 style="font-size:15px;font-weight:700;margin:0 0 18px">Редактировать категорию «{{ $editing->name }}»</h2>
                    @include('admin.categories._form', ['category' => $editing])
                </div>
            </div>
        @else
            <div class="card" style="padding:40px;text-align:center;color:var(--text-muted)">
                Выберите категорию слева, чтобы отредактировать её здесь.
            </div>
        @endif
    </div>
</div>
@endsection
