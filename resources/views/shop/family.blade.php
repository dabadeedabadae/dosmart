@extends('shop.layout')
@section('title', 'Баланс родственника — Dosmart')
@section('body-class', 'checkout-page')
@php($tenge = fn (?int $minor) => number_format(($minor ?? 0) / 100, 0, ',', ' ').' ₸')
@section('content')
@include('shop.partials.family-styles')
<div class="checkout-wrap family" style="max-width:780px">
    <div class="family-row">
        <span class="family-kicker">Для родственников</span>
        <form method="post" action="{{ route('family.logout') }}">@csrf<button class="btn btn-ghost">Выйти</button></form>
    </div>
    <h1>{{ $prisoner->full_name }}</h1>
    <p class="intro">{{ $profile['institution'] }}@if($profile['squad']) · отряд {{ $profile['squad'] }}@endif · {{ $profile['condition'] }} условия</p>

    @if(session('deposit'))
        @php($created = $requests->firstWhere('id', session('deposit')))
        @if($created)
        <div class="family-pay" role="status">
            <strong>Заявка {{ $created->number() }} на {{ $tenge($created->amount_minor) }} принята.</strong><br>
            @if($kaspiUrl)Оплатите по ссылке Kaspi: <a href="{{ $kaspiUrl }}" target="_blank" rel="noopener">{{ $kaspiUrl }}</a>. @endif
            В комментарии к переводу укажите номер заявки <strong>{{ $created->number() }}</strong>. Сотрудник сверит поступление и зачислит деньги на баланс — обычно в часы работы магазина ({{ config('pilot.service_hours') }}). Фискальный чек придёт в приложение Kaspi.
        </div>
        @endif
    @endif

    <section class="family-metrics" aria-label="Баланс и лимит">
        <div class="family-metric main"><span>Баланс Dosmart</span><strong>{{ $tenge($profile['balance_minor']) }}</strong><small>На эти деньги можно покупать товары на планшете</small></div>
        <div class="family-metric">
            <span>Лимит покупок в этом месяце</span>
            @if($profile['monthly_limit_minor'] === null)
                <strong>Без лимита</strong><small>Ограничение не установлено</small>
            @else
                <strong>Осталось {{ $tenge($profile['monthly_remaining_minor']) }}</strong>
                <small>Лимит {{ $tenge($profile['monthly_limit_minor']) }}@if($profile['limit_carried_minor'] > 0) + перенос {{ $tenge($profile['limit_carried_minor']) }} с прошлых месяцев@endif. Доставка в лимит не входит.</small>
            @endif
        </div>
    </section>

    <section class="form-card family-card">
        <h2>Попросить пополнить баланс</h2>
        <p class="form-hint">Заполните заявку, затем оплатите через Kaspi. Деньги зачисляются после проверки сотрудником. Учреждение может ограничивать число и сумму переводов в месяц — если перевод нельзя зачислить, магазин свяжется с вами и вернёт деньги.</p>
        @if($errors->any())<div class="form-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="post" action="{{ route('family.deposit') }}">
            @csrf
            <div class="form-group"><label class="form-label" for="sender-name">Ваши ФИО (отправитель)</label><input class="form-control" id="sender-name" name="sender_name" required maxlength="255" value="{{ old('sender_name') }}"></div>
            <div class="form-group"><label class="form-label" for="sender-phone">Ваш WhatsApp</label><input class="form-control" id="sender-phone" name="sender_phone" required inputmode="tel" placeholder="+7 705 621 8748" value="{{ old('sender_phone') }}"></div>
            <div class="form-group"><label class="form-label" for="amount">Сумма, ₸</label><input class="form-control" id="amount" name="amount" type="number" min="500" max="1000000" step="1" required value="{{ old('amount') }}" placeholder="10000"></div>
            <label class="family-check"><input type="checkbox" name="consent" value="1" required> Согласен на обработку моих ФИО и номера для зачисления перевода и связи со мной.</label>
            <button class="btn btn-primary btn-lg btn-block">Отправить заявку</button>
        </form>
    </section>

    @if($requests->isNotEmpty())
    <section class="form-card family-card">
        <h2>Последние заявки</h2>
        <ul class="family-list">
            @foreach($requests as $deposit)
                <li><span><strong>{{ $deposit->number() }}</strong> · {{ $deposit->created_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}<br><span class="family-status">{{ \App\Models\DepositRequest::STATUSES[$deposit->status] }}@if($deposit->status === 'rejected' && $deposit->reject_reason): {{ $deposit->reject_reason }}@endif</span></span><strong>{{ $tenge($deposit->amount_minor) }}</strong></li>
            @endforeach
        </ul>
    </section>
    @endif
    <p class="form-hint">Этот телефон помнит вход 30 дней. Если родственник выдаст новый код в настройках, вход закроется — попросите новый код.</p>
</div>
@endsection
