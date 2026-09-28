@extends('shop.layout')
@section('title', 'Страница не найдена — DOSMART')
@section('content')
<div class="empty"><h1>Страница не найдена</h1><p class="intro">Если вы открывали корзину, проверьте код из сообщения или попросите близкого отправить ссылку ещё раз.</p><a href="{{ route('shop.index') }}" class="btn btn-primary">Вернуться в магазин</a></div>
@endsection
