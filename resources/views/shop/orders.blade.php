@extends('shop.layout')
@section('title', 'Мои заказы — DOSMART')
@section('content')
<div class="eyebrow">ЛИЧНЫЙ КАБИНЕТ</div><h1>Мои заказы</h1><p class="intro">{{ auth('customer')->user()->phone }}</p>
@forelse($orders as $order)
<article class="form-card" style="margin-bottom:16px">
    <div class="order-heading"><h2>{{ $order->order_number }}</h2><span class="status-pill">{{ $order->customer_status_label }}</span></div>
    <p class="intro">{{ $order->created_at->format('d.m.Y H:i') }} · {{ $order->institution_name }}</p>
    @foreach($order->items as $item)<div class="summary-row"><span>{{ $item->product_name }} × {{ $item->quantity }}</span><strong>{{ number_format($item->total, 0, ',', ' ') }} ₸</strong></div>@endforeach
    <div class="summary-row"><span>Доставка</span><span>{{ number_format($order->delivery_fee, 0, ',', ' ') }} ₸</span></div>
    @if($draft = $order->draft)<a class="btn btn-primary" style="margin-top:16px" href="{{ route('pilot.payment',$draft->code) }}">Оплата и статус</a>@endif
    <div class="summary-total"><span>Итого</span><span>{{ number_format($order->total, 0, ',', ' ') }} ₸</span></div>
</article>
@empty<div class="empty"><h3>Пока нет заказов</h3><p>Выберите товары и оформите свой первый заказ.</p><a class="btn btn-primary" style="margin-top:20px" href="{{ route('shop.index') }}">Перейти в каталог</a></div>@endforelse
@include('shop.partials.pagination', ['paginator' => $orders])
@endsection
