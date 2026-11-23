@extends('admin.layouts.app')
@include('admin.prisoners.styles')
@section('title', 'Добавить человека')
@section('content')
<div class="limits-page">
<a class="limits-back" href="{{ route('admin.prisoners.people', request()->only('institution_id')) }}">← К списку</a>
<header class="limits-hero"><div><span class="limits-kicker">Учёт лимитов</span><h1 class="page-title">Добавить человека</h1><p>На планшете человек находит себя по ФИО и отряду и сам ставит пароль. Баланс после создания — 0 ₸. Целый список удобнее загрузить через «Импорт» на странице учреждения.</p></div></header>
<section class="card limits-panel" style="max-width:760px">
    <h2 class="limits-heading">Данные получателя</h2>
    <form method="post" action="{{ route('admin.prisoners.store') }}">
        @csrf
        <div class="form-group"><label>ФИО <input class="form-control" name="full_name" required maxlength="255" value="{{ old('full_name') }}"></label></div>
        <div class="limits-fields">
            <div class="form-group"><label>Отряд <input class="form-control" name="squad" maxlength="50" value="{{ old('squad') }}" placeholder="Например, 7"></label></div>
            <div class="form-group"><label>ИИН (необязательно) <input class="form-control" name="iin" pattern="[0-9]{12}" maxlength="12" inputmode="numeric" autocomplete="off" value="{{ old('iin') }}"></label></div>
        </div>
        <div class="form-group"><label>Учреждение
            <select class="form-control" name="institution_id" required>
                <option value="">Выберите учреждение</option>
                @foreach($institutions as $institution)<option value="{{ $institution->id }}" @selected(old('institution_id', request('institution_id')) == $institution->id)>{{ $institution->name }}</option>@endforeach
            </select>
        </label></div>
        @include('admin.prisoners.condition', ['prisoner' => null])
        <div class="limits-actions">
            <button class="btn btn-primary">Создать карточку</button>
            <a class="btn btn-ghost" href="{{ route('admin.prisoners.people', request()->only('institution_id')) }}">Отмена</a>
        </div>
    </form>
</section>
</div>
@endsection
