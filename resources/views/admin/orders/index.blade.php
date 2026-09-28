@extends('admin.layouts.app')
@section('title', 'Заказы')

@section('content')
<div class="page-header">
    <h1 class="page-title">Заказы</h1>
    <div class="filter-wrap">
        <form method="GET">
            <select name="status" class="form-control filter-select" onchange="this.form.submit()">
                <option value="">Все статусы</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>
</div>

<div class="card" style="padding:20px;margin-bottom:20px"><h2 style="font-size:16px;margin-bottom:12px">Заявки с терминала</h2><p>Получено заявок: <strong>{{ $metrics['submission_received'] ?? 0 }}</strong> · Подтверждено оплат с терминала: <strong>{{ $metrics['payment_confirmed'] ?? 0 }}</strong></p><p style="font-size:12px;margin-top:8px">Новая заявка → связь с родственником → согласование доставки → ссылка на оплату → проверка в Kaspi Pay. Счётчик оплат включает заказы предыдущего сценария.</p></div>
<form method="get" style="display:flex;gap:12px;margin-bottom:20px"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Номер заказа"><button class="btn btn-primary">Найти</button></form>
@if($drafts->isNotEmpty())<details class="card" style="padding:20px;margin-bottom:20px"><summary>Черновики: последние {{ $drafts->count() }} активных</summary>@foreach($drafts as $draft)<p style="margin-top:12px">{{ $draft->code }} · {{ $draft->source === 'terminal' ? 'Терминал' : 'Сайт' }} · до {{ $draft->expires_at->format('d.m.Y H:i') }}</p>@endforeach</details>@endif
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Заказ</th>
                    <th>Получатель</th>
                    <th>Доставка</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td style="font-weight:700;color:var(--text);white-space:nowrap">
                        {{ $order->order_number }}
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--text)">{{ $order->prisoner_name }}</div>
                        <div style="font-size:12px">{{ $order->institution_name }} · {{ $order->contact_phone }}</div>
                        <div style="font-size:11.5px;color:var(--text-muted);margin-top:1px">
                            {{ $order->items_count }}
                            @php
                                $n = $order->items_count;
                                echo $n === 1 ? 'товар' : ($n >= 2 && $n <= 4 ? 'товара' : 'товаров');
                            @endphp
                        </div>
                    </td>
                    <td style="color:var(--text-secondary)">
{{ $order->delivery_type === 'unconfirmed' ? 'Не согласована' : number_format($order->delivery_fee,0,',',' ').' ₸' }}
                    </td>
                    <td style="font-weight:700;white-space:nowrap">
                        {{ number_format($order->total, 0, ',', ' ') }} ₸ @if($order->delivery_type === 'unconfirmed')<small>(товары)</small>@endif
                    </td>
                    <td>
                        @php
                            $cls = match($order->payment_status) {
                                'paid'     => 'badge-success',
                                'refunded' => 'badge-danger',
                                default    => 'badge-warning',
                            };
                            $lbl = match($order->payment_status) {
                                'paid'     => 'Оплачено',
                                'refunded' => 'Возврат',
                                default    => 'Ожидает оплаты',
                            };
                            if ($order->status === 'cancelled') { $cls = 'badge-danger'; $lbl = 'Отменён'; }
                        @endphp
                        <span class="badge {{ $cls }}">
                            <span class="badge-dot"></span>{{ $order->status_label }}
                        </span>
                    </td>
                    <td style="color:var(--text-secondary);white-space:nowrap;font-size:13px">
                        {{ $order->created_at->translatedFormat('d M') }}
                    </td>
                    <td style="width:44px">
                        <a href="{{ route('admin.orders.show', $order) }}" class="icon-btn" title="Открыть заказ">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:48px 16px;color:var(--text-muted)">
                        Заказов нет
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($orders->hasPages())
        <div style="padding:12px 16px;border-top:1px solid var(--border)">
            {{ $orders->withQueryString()->links('admin.pagination') }}
        </div>
    @endif
</div>
@endsection
