@extends('admin.layouts.app')
@include('admin.prisoners.styles')
@section('title', 'Заявки родственников')
@php($tenge = fn (int $minor) => number_format($minor / 100, 2, ',', ' ').' ₸')
@section('content')
<div class="limits-page">
<a class="limits-back" href="{{ route('admin.prisoners.index') }}">← Учёт лимитов</a>
<header class="limits-hero">
    <div><span class="limits-kicker">Учёт лимитов</span>
    <h1 class="page-title">Заявки родственников</h1>
    <p>Родственник по коду из кабинета просит пополнить счёт и платит через Kaspi с номером заявки в комментарии. Сверьте поступление в Kaspi Pay и зачислите.</p></div>
</header>
<nav class="limits-tabs" aria-label="Статус">
    @foreach(\App\Models\DepositRequest::STATUSES as $key => $label)
        <a class="limits-tab @if($status === $key) is-active @endif" href="{{ route('admin.prisoners.requests', ['status' => $key]) }}">{{ $label }} <span>{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>
<section class="card limits-panel">
    @forelse($requests as $deposit)
        @php($relative = $relative[$deposit->id])
        <article class="limits-request">
            <div>
                <h3>{{ $deposit->number() }} · {{ $tenge($deposit->amount_minor) }}</h3>
                <p class="limits-muted">Отправитель: <strong>{{ $deposit->sender_name }}</strong>, {{ $deposit->sender_phone }}<br>
                Получатель: <a href="{{ route('admin.prisoners.show', $deposit->prisoner) }}">{{ $deposit->prisoner->full_name }}</a>, {{ $deposit->prisoner->institution->name }}@if($deposit->prisoner->squad), отряд {{ $deposit->prisoner->squad }}@endif<br>
                Создана {{ $deposit->created_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}@if($deposit->handled_at) · обработана {{ $deposit->handled_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}@endif</p>
                @if($deposit->status === 'rejected')<p class="limits-muted">Причина: {{ $deposit->reject_reason }}</p>@endif
                @if($deposit->status === 'new' && ($relative['count'] >= 2 || ($relative['cap_minor'] && $relative['sum_minor'] + $deposit->amount_minor > $relative['cap_minor'])))
                    <p class="limits-warn">В этом месяце уже зачислено от родственников: {{ $relative['count'] }} на {{ $tenge($relative['sum_minor']) }}. Ст. 108 УИК (средняя и выше безопасность): не более 2 переводов и 20 МРП@if($relative['cap_minor']) ({{ $tenge($relative['cap_minor']) }})@endif в месяц.</p>
                @endif
            </div>
            <div>
                @if($deposit->status === 'new')
                    <form method="post" action="{{ route('admin.prisoners.requests.credit', $deposit) }}" class="limits-form">
                        @csrf
                        <div class="limits-fields">
                            <div class="form-group"><label>Поступило, ₸ <input class="form-control" name="amount" type="number" step="0.01" min="0.01" required value="{{ $deposit->amount_minor / 100 }}"></label></div>
                            <div class="form-group"><label>№ платежа Kaspi <input class="form-control" name="payment_reference" maxlength="100" required></label></div>
                        </div>
                        <label class="limits-check"><input type="checkbox" name="payment_verified" value="1" required> Поступление сверено в Kaspi Pay</label>
                        <button class="btn btn-primary">Зачислить на счёт</button>
                    </form>
                    <details class="limits-more"><summary>Отклонить</summary>
                        <form method="post" action="{{ route('admin.prisoners.requests.reject', $deposit) }}" class="limits-form">
                            @csrf
                            <div class="form-group"><label>Причина (увидит родственник) <input class="form-control" name="reason" maxlength="255" required placeholder="Оплата не поступила"></label></div>
                            <button class="btn btn-danger-soft">Отклонить заявку</button>
                        </form>
                    </details>
                @elseif($deposit->transfer_id)
                    <a class="btn" href="{{ route('admin.prisoners.transfers.receipt', [$deposit->prisoner, $deposit->transfer_id]) }}" target="_blank" rel="noopener">Квитанция о зачислении</a>
                @endif
            </div>
        </article>
    @empty
        <p class="limits-empty">Заявок нет.</p>
    @endforelse
    {{ $requests->links() }}
</section>
</div>
@endsection
