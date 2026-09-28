@extends('admin.layouts.app')
@section('title', 'Отчёт')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="page-header">
    <h1 class="page-title">Отчёт</h1>
    <div style="display:flex;align-items:center;gap:8px">
        <form method="GET">
            <select name="period" class="form-control" style="width:180px" onchange="this.form.submit()">
                <option value="30"  {{ $period == 30  ? 'selected' : '' }}>Последние 30 дней</option>
                <option value="7"   {{ $period == 7   ? 'selected' : '' }}>Последние 7 дней</option>
                <option value="90"  {{ $period == 90  ? 'selected' : '' }}>Последние 90 дней</option>
            </select>
        </form>
    </div>
</div>

{{-- Stat cards --}}
<div class="stats-grid">
    <div class="card stat-card">
        <div class="stat-label">Заказов за период</div>
        <div class="stat-value">{{ $totalOrders }}</div>
        @if($prevOrders > 0)
            @php $diff = round(($totalOrders - $prevOrders) / $prevOrders * 100); @endphp
            <div class="stat-trend {{ $diff >= 0 ? 'up' : 'down' }}">
                {{ $diff >= 0 ? '↑' : '↓' }} {{ abs($diff) }}% к прошлому периоду
            </div>
        @else
            <div class="stat-trend neutral">Нет данных за прошлый период</div>
        @endif
    </div>

    <div class="card stat-card">
        <div class="stat-label">Выручка</div>
        <div class="stat-value" style="font-size:22px">{{ number_format($revenue, 0, ',', ' ') }} ₸</div>
        @if($prevRevenue > 0)
            @php $diffR = round(($revenue - $prevRevenue) / $prevRevenue * 100); @endphp
            <div class="stat-trend {{ $diffR >= 0 ? 'up' : 'down' }}">
                {{ $diffR >= 0 ? '↑' : '↓' }} {{ abs($diffR) }}% к прошлому периоду
            </div>
        @else
            <div class="stat-trend neutral">Нет данных за прошлый период</div>
        @endif
    </div>

    <div class="card stat-card">
        <div class="stat-label">Средний чек</div>
        <div class="stat-value" style="font-size:22px">{{ number_format($avgOrder, 0, ',', ' ') }} ₸</div>
        @if($prevAvg > 0)
            @php $diffA = round(($avgOrder - $prevAvg) / $prevAvg * 100); @endphp
            <div class="stat-trend {{ $diffA >= 0 ? 'up' : 'down' }}">
                {{ $diffA >= 0 ? '↑' : '↓' }} {{ abs($diffA) }}% к прошлому периоду
            </div>
        @else
            <div class="stat-trend neutral">Нет данных за прошлый период</div>
        @endif
    </div>

    <div class="card stat-card">
        <div class="stat-label">Активных товаров</div>
        <div class="stat-value">{{ $activeProducts }}</div>
        <div class="stat-trend neutral">{{ $newProducts }} новых за эту неделю</div>
    </div>
</div>

{{-- Bar chart --}}
<div class="card" style="margin-bottom:18px">
    <div class="chart-wrap">
        <div class="chart-label">Заказы по дням</div>
        <div class="chart-sub">Последние {{ $period }} дней</div>
        <div style="position:relative;height:220px">
            <canvas id="ordersChart"></canvas>
        </div>
    </div>
</div>

{{-- Recent orders --}}
<div class="card">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px">
        Последние заказы
    </div>
    @forelse($recentOrders as $order)
    <div class="recent-item">
        <div class="recent-name">{{ $order->prisoner_name }}</div>
        @php
            $cls = $order->payment_status === 'paid' ? 'badge-success' : ($order->status === 'cancelled' ? 'badge-danger' : 'badge-warning');
            $lbl = $order->payment_status === 'paid' ? 'Оплачено' : ($order->status === 'cancelled' ? 'Отменён' : 'Ожидает оплаты');
        @endphp
        <span class="badge {{ $cls }}">{{ $lbl }}</span>
        <div class="recent-amount">{{ number_format($order->total, 0, ',', ' ') }} ₸</div>
    </div>
    @empty
    <div style="padding:40px;text-align:center;color:var(--text-muted)">Заказов нет</div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
const labels = @json($chartLabels);
const data   = @json($chartData);

const ctx = document.getElementById('ordersChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            data,
            backgroundColor: 'oklch(53% 0.17 258 / 18%)',
            borderColor:     'oklch(53% 0.17 258)',
            borderWidth: 1.5,
            borderRadius: 5,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.parsed.y + ' заказов'
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { family: "'Plus Jakarta Sans', system-ui", size: 11 }, color: 'oklch(63% 0.012 255)' }
            },
            y: {
                grid: { color: 'oklch(90% 0.007 255)' },
                ticks: {
                    precision: 0,
                    font: { family: "'Plus Jakarta Sans', system-ui", size: 11 },
                    color: 'oklch(63% 0.012 255)'
                },
                beginAtZero: true,
            }
        }
    }
});
</script>
@endpush
