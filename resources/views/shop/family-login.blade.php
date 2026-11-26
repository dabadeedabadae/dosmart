@extends('shop.layout')
@section('title', 'Для родственников — Dosmart')
@section('body-class', 'checkout-page')
@section('content')
@include('shop.partials.family-styles')
<div class="checkout-wrap family" style="max-width:620px">
    <span class="family-kicker">Для родственников</span>
    <h1>Счёт и лимиты вашего близкого</h1>
    <p class="intro">Попросите родственника открыть на планшете Dosmart «Личный кабинет → Настройки» и продиктовать вам код из 6 цифр. Введите его вместе с фамилией родственника — и увидите счёт и лимит на покупки, а также сможете попросить пополнение.</p>
    @if($errors->any())<div class="form-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="post" action="{{ route('family.enter') }}" class="form-card family-card">
        @csrf
        <div class="form-group"><label class="form-label" for="family-surname">Фамилия родственника (кто в учреждении)</label>
            <input class="form-control" id="family-surname" name="surname" required maxlength="100" autocomplete="off" placeholder="Например: Иванов" value="{{ old('surname') }}">
        </div>
        <div class="form-group"><label class="form-label" for="family-code">Код для родственников</label>
            <input class="form-control family-code-input" id="family-code" name="code" required maxlength="9" inputmode="numeric" autocomplete="off" placeholder="123 456" value="{{ old('code') }}">
            <p class="form-hint">6 цифр. Пробел вводить не нужно.</p>
        </div>
        <label class="family-check"><input type="checkbox" name="remember" value="1" checked> Запомнить на этом телефоне на 30 дней</label>
        <button class="btn btn-primary btn-lg btn-block">Открыть</button>
    </form>
    <p class="form-hint">Код не даёт права покупать или тратить деньги — только смотреть счёт, лимит и отправить просьбу о пополнении. На чужом телефоне снимите галочку «Запомнить».</p>
</div>
@endsection
