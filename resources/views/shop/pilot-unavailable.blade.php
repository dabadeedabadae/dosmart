@extends('shop.layout')
@section('content')
<div class="empty"><h1>Корзину нужно обновить</h1><p class="intro">Некоторые товары больше недоступны. Попросите близкого отправить новую корзину или выберите товары самостоятельно.</p><a class="btn btn-primary" href="{{ route('shop.index') }}">Открыть каталог</a></div>
@endsection
