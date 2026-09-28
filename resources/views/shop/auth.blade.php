@extends('shop.layout')
@section('title', ($register ? 'Регистрация' : 'Вход') . ' — DOSMART')
@section('content')
<div class="auth-layout">
    <div><div class="eyebrow">ЛИЧНЫЙ КАБИНЕТ</div><h1>Забота начинается<br>с простых вещей.</h1><p class="intro">Соберите нужные товары, оформите заказ и следите за его статусом в своём аккаунте.</p><a href="{{ route('shop.index') }}" class="btn btn-ghost">← В каталог</a></div>
    <form method="post" action="{{ route($register ? 'shop.register' : 'shop.login') }}" class="form-card">
        @csrf
        <h2 style="margin-bottom:8px">{{ $register ? 'Создать аккаунт' : 'С возвращением' }}</h2>
        <p class="intro">{{ $register ? 'Для регистрации нужны телефон и пароль.' : 'Войдите по номеру телефона и паролю.' }}</p>
        @if($errors->any())<div class="form-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="form-group"><label for="phone" class="form-label">Номер телефона</label><input class="form-control" id="phone" name="phone" type="tel" autocomplete="tel" placeholder="+7 700 123 45 67" value="{{ old('phone') }}" required></div>
        <div class="form-group"><label for="password" class="form-label">Пароль</label><input class="form-control" id="password" name="password" type="password" autocomplete="{{ $register ? 'new-password' : 'current-password' }}" @if($register) minlength="8" @endif maxlength="128" required>@if($register)<p class="form-hint">Не менее 8 символов</p>@endif</div>
        @if($register)<div class="form-group"><label for="password_confirmation" class="form-label">Повторите пароль</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>@endif
        <button class="btn btn-primary btn-block btn-lg">{{ $register ? 'Зарегистрироваться' : 'Войти' }}</button>
        <p style="margin-top:20px;text-align:center"><a href="{{ route($register ? 'shop.login' : 'shop.register') }}">{{ $register ? 'Уже есть аккаунт? Войти' : 'Нет аккаунта? Зарегистрироваться' }}</a></p>
        <p style="margin-top:18px;text-align:center"><a href="{{ route('admin.login') }}">Вход администратора по логину</a></p>
    </form>
</div>
@endsection
