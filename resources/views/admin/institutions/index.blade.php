@extends('admin.layouts.app')
@section('title', 'Учреждения')

@section('content')
<div class="page-header">
    <h1 class="page-title">Учреждения</h1>
    <a href="{{ route('admin.institutions.create') }}" class="btn btn-primary">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M12 5v14M5 12h14"/>
        </svg>
        Добавить учреждение
    </a>
</div>

@php
    $editing = request('edit') ? $institutions->firstWhere('id', (int) request('edit')) : null;
@endphp

<div style="display:flex;gap:24px;align-items:flex-start">
    <div style="flex:0 0 auto;width:640px;max-width:100%">
        @if($institutions->isEmpty())
            <div class="card" style="padding:48px;text-align:center;color:var(--text-muted)">
                Учреждений нет. Добавьте первое.
            </div>
        @else
            <div class="card">
                @foreach($institutions as $institution)
                <div class="cat-list-item">
                    <div class="cat-dot {{ !$institution->is_active ? 'empty' : '' }}"></div>
                    <div style="flex:1;min-width:0">
                        <div class="cat-list-name">{{ $institution->name }}</div>
                        <div class="cat-list-count">
                            {{ $institution->city }} · {{ $institution->orders_count }} заказов
                            <span class="badge {{ $institution->is_active ? 'badge-success' : 'badge-neutral' }}">{{ $institution->is_active ? 'Активно' : 'Неактивно' }}</span>
                        </div>
                    </div>
                    <div class="cat-list-actions">
                        <form method="POST" action="{{ route('admin.institutions.activity', $institution) }}" style="display:contents">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $institution->is_active ? '0' : '1' }}">
                            <button type="submit" class="icon-btn" title="{{ $institution->is_active ? 'Отключить' : 'Включить' }}" aria-label="{{ $institution->is_active ? 'Отключить' : 'Включить' }} учреждение {{ $institution->name }}">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 2v10M6 5a9 9 0 1 0 12 0"/></svg>
                            </button>
                        </form>
                        <a href="{{ route('admin.institutions.index', ['edit' => $institution->id]) }}"
                           class="icon-btn {{ $editing && $editing->id === $institution->id ? 'active' : '' }}" title="Редактировать">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.institutions.destroy', $institution) }}"
                              data-name="{{ $institution->name }}"
                              onsubmit="return confirm('Удалить учреждение «' + this.dataset.name + '»?')"
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
                    <h2 style="font-size:15px;font-weight:700;margin:0 0 18px">Редактировать учреждение «{{ $editing->name }}»</h2>
                    @include('admin.institutions._form', ['institution' => $editing])
                </div>
            </div>
        @else
            <div class="card" style="padding:40px;text-align:center;color:var(--text-muted)">
                Выберите учреждение слева, чтобы отредактировать его здесь.
            </div>
        @endif
    </div>
</div>
@endsection
