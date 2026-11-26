@extends('admin.layouts.app')
@include('admin.prisoners.styles')
@section('title', $institution?->name ?? 'Все учреждения')
@section('content')
<div class="limits-page">
<a class="limits-back" href="{{ route('admin.prisoners.index') }}">← Учёт лимитов</a>
<header class="limits-hero">
    <div><span class="limits-kicker">Учёт лимитов</span>
    <h1 class="page-title">{{ $institution?->name ?? 'Все учреждения' }}</h1><p>Список получателей, отряды, лимиты и регистрация на планшете.</p></div>
    <a class="btn" href="{{ route('admin.prisoners.create', $institution ? ['institution_id' => $institution->id] : []) }}">Добавить человека</a>
</header>
@if(session('import_problems'))
    <div class="alert alert-danger">@foreach(session('import_problems') as $problem)<div>{{ $problem }}</div>@endforeach</div>
@endif
@php($base = array_filter(request()->only(['q', 'institution_id', 'condition', 'squad'])))
<form class="limits-search" method="get" action="{{ route('admin.prisoners.people') }}" role="search">
    @foreach(array_filter(request()->only(['institution_id', 'condition', 'squad', 'limit', 'registered', 'review'])) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    <label class="sr-only" for="people-q">ФИО или ИИН</label>
    <input class="form-control" id="people-q" type="search" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Поиск по ФИО или ИИН" autocomplete="off">
    <button class="btn btn-primary">Найти</button>
</form>
<nav class="limits-tabs" aria-label="Быстрые фильтры">
    <a class="limits-tab @if(! request('limit') && ! request('registered') && ! request('review')) is-active @endif" href="{{ route('admin.prisoners.people', $base) }}">Все</a>
    <a class="limits-tab @if(request('limit') === 'with') is-active @endif" href="{{ route('admin.prisoners.people', $base + ['limit' => 'with']) }}">С лимитом <span>{{ $counts['with'] }}</span></a>
    <a class="limits-tab @if(request('limit') === 'without') is-active @endif" href="{{ route('admin.prisoners.people', $base + ['limit' => 'without']) }}">Без лимита <span>{{ $counts['without'] }}</span></a>
    <a class="limits-tab @if(request('registered') === 'no') is-active @endif" href="{{ route('admin.prisoners.people', $base + ['registered' => 'no']) }}">Не зарегистрированы <span>{{ $counts['unregistered'] }}</span></a>
    <a class="limits-tab @if(request('review') === 'waiting') is-active @endif" href="{{ route('admin.prisoners.people', $base + ['review' => 'waiting']) }}">Ждут проверки <span>{{ $counts['review'] }}</span></a>
</nav>
@php($activeFilters = count(array_filter(request()->only(['condition', 'squad', 'limit']))))
<details class="card limits-panel limits-filters" data-desktop-open @if($activeFilters) open @endif>
    <summary class="limits-heading">Фильтры @if($activeFilters)<span class="badge badge-accent">{{ $activeFilters }}</span>@endif</summary>
    <form method="get" action="{{ route('admin.prisoners.people') }}">
        @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
        <div class="limits-fields">
            <div class="form-group"><label>Учреждение
                <select class="form-control" name="institution_id">
                    <option value="">Все учреждения</option>
                    @foreach($institutions as $option)
                        <option value="{{ $option->id }}" @selected($institution?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label></div>
            <div class="form-group"><label>Отряд <input class="form-control" name="squad" maxlength="50" value="{{ request('squad') }}"></label></div>
            <div class="form-group"><label>Условия содержания
                <select class="form-control" name="condition">
                    <option value="">Все условия</option>
                    @foreach(\App\Models\Prisoner::CONDITIONS as $value => $label)
                        <option value="{{ $value }}" @selected(request('condition') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label></div>
            <div class="form-group"><label>Лимит покупок
                <select class="form-control" name="limit">
                    <option value="">Все</option>
                    <option value="with" @selected(request('limit') === 'with')>С лимитом</option>
                    <option value="without" @selected(request('limit') === 'without')>Без лимита</option>
                </select>
            </label></div>
        </div>
        <button class="btn btn-primary">Применить</button>
        <a class="btn btn-ghost" href="{{ route('admin.prisoners.people', $institution ? ['institution_id' => $institution->id] : []) }}">Сбросить</a>
    </form>
</details>
<section class="card limits-panel">
    <h2 class="limits-heading">Получатели <span class="badge badge-accent">{{ $prisoners->total() }}</span></h2>
    <ul class="limits-people">
        @forelse($prisoners as $prisoner)
            <li><a class="limits-person" href="{{ route('admin.prisoners.show', $prisoner) }}">
                <span><strong>{{ $prisoner->full_name }}</strong><small>{{ $prisoner->institution->name }}@if($prisoner->squad) · отряд {{ $prisoner->squad }}@endif</small></span>
                <span class="limits-person-meta">
                    <span class="limits-balance">{{ number_format($prisoner->balance_minor / 100, 0, ',', ' ') }} ₸</span>
                    <span class="badge badge-accent">{{ app(\App\Services\PrisonerAccounts::class)->conditionLabel($prisoner) }}</span>
                    @if(($prisoner->spending_mode ?? 'unlimited') === 'unlimited')
                        <span class="badge badge-success">Без лимита</span>
                    @else
                        <span class="badge badge-warning">Лимит: {{ $prisoner->spending_mode === 'norm' ? 'норма МРП' : number_format($prisoner->monthly_limit_minor / 100, 0, ',', ' ').' ₸' }}</span>
                    @endif
                    @unless($prisoner->registered_at)<span class="badge badge-neutral">Не зарегистрирован</span>@endunless
                    @if($prisoner->needsReview())<span class="badge badge-danger">Ждёт проверки</span>@endif
                </span>
            </a></li>
        @empty
            <li class="limits-empty">Карточки не найдены.</li>
        @endforelse
    </ul>
    {{ $prisoners->links() }}
</section>
@if($institution)
    <div class="limits-columns">
        <section class="card limits-panel">
            <h2 class="limits-heading">Планшеты учреждения</h2>
            <p class="limits-muted">Сотрудник вводит этот код на планшете один раз («Личный кабинет» → «Настройка планшета»). После этого планшет показывает людей и отряды только этого учреждения.</p>
            @if($institution->tablet_code)
                <p class="limits-code">{{ $institution->tablet_code }}</p>
            @else
                <p class="limits-muted"><strong>Код ещё не создан.</strong></p>
            @endif
            <form method="post" action="{{ route('admin.institutions.tablet-code', $institution) }}" @if($institution->tablet_code) onsubmit="return confirm('Создать новый код? Все планшеты этого учреждения нужно будет настроить заново.')" @endif>
                @csrf<button class="btn">{{ $institution->tablet_code ? 'Выдать новый код' : 'Создать код' }}</button>
            </form>
        </section>
        <section class="card limits-panel">
            <h2 class="limits-heading">Импорт списка</h2>
            <p class="limits-muted">Одна строка — один человек: <code>ФИО; отряд; ИИН; условия</code>. ИИН и условия — необязательно (условия: обычные, облегчённые, строгие). Существующим карточкам обновится отряд.</p>
            <form method="post" action="{{ route('admin.institutions.import', $institution) }}" class="limits-form">
                @csrf
                <textarea class="form-control" name="rows" rows="6" required placeholder="Иванов Иван Иванович; 7&#10;Петров Пётр Петрович; 3; 900101300123; строгие"></textarea>
                <button class="btn btn-primary">Загрузить</button>
            </form>
        </section>
    </div>
    @include('admin.prisoners.spending')
    @include('admin.prisoners.limits')
@endif
</div>
@endsection
