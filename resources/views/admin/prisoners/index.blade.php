@extends('admin.layouts.app')
@include('admin.prisoners.styles')
@section('title', 'Учёт лимитов')
@section('content')
<div class="limits-page">
    <header class="limits-hero">
        <div>
            <span class="limits-kicker">Dosmart · Админка</span>
            <h1 class="page-title">Учёт лимитов</h1>
            <p>Карточки по ФИО и отряду, пополнение счёта, лимиты покупок и нормы передач.</p>
        </div>
        <div class="limits-actions">
            <a class="btn @if($pendingRequests) btn-primary @endif" href="{{ route('admin.prisoners.requests') }}">Заявки родственников @if($pendingRequests)<span class="badge badge-warning">{{ $pendingRequests }}</span>@endif</a>
            <a class="btn" href="{{ route('admin.prisoners.people') }}">Все учреждения →</a>
        </div>
    </header>
    <section aria-labelledby="limits-institutions">
        <h2 class="limits-heading" id="limits-institutions">Учреждения</h2>
        <div class="limits-grid">
            @forelse($institutions as $institution)
                <a class="card limits-tile" href="{{ route('admin.prisoners.people', ['institution_id' => $institution->id]) }}">
                    <span class="limits-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 21h16M6 21V5l6-2 6 2v16M9 8h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1"/></svg>
                    </span>
                    <div><h3>{{ $institution->name }}</h3><p class="limits-muted">{{ $institution->city }}</p></div>
                    <div class="limits-tile-meta"><span class="badge badge-accent">Карточек: {{ $institution->prisoners_count }}</span><span aria-hidden="true">→</span></div>
                </a>
            @empty
                <p class="limits-empty">Добавьте учреждение, чтобы начать учёт.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
