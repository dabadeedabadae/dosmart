@extends('shop.layout')
@section('title', 'Срок действия корзины истёк — DOSMART')
@section('content')
<div class="empty"><h1>Срок действия корзины истёк</h1><p class="intro">Черновик действует 4 дня. Попросите близкого отправить новую корзину или выберите товары в каталоге.</p><a href="{{ route('shop.index') }}" class="btn btn-primary">Вернуться в магазин</a></div>
@endsection
