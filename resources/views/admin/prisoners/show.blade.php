@extends('admin.layouts.app')
@include('admin.prisoners.styles')
@section('title', 'Карточка получателя')
@php
    $tenge = fn (?int $minor) => $minor === null ? '—' : number_format($minor / 100, 2, ',', ' ').' ₸';
    $statusBadge = ['pending' => ['Ожидает перевода', 'warning'], 'confirmed' => ['Зачислено', 'success'], 'rejected' => ['Отклонено', 'neutral'], 'reversed' => ['Сторнировано', 'danger']];
    $limit = $profile['monthly_limit_minor'];
    $spent = $profile['monthly_spent_minor'];
    $available = $limit === null ? null : $limit + $profile['limit_carried_minor'];
    $percent = $available ? min(100, (int) round($spent / max(1, $available) * 100)) : ($available === 0 ? 100 : 0);
@endphp
@section('content')
<div class="limits-page">
<a class="limits-back" href="{{ route('admin.prisoners.people', ['institution_id' => $prisoner->institution_id]) }}">← К списку учреждения</a>


<header class="limits-hero">
    <div>
        <span class="limits-kicker">Карточка получателя</span>
        <h1 class="page-title">{{ $prisoner->full_name }}</h1>
        <p>{{ $prisoner->institution->name }} · отряд {{ $prisoner->squad ?? 'не указан' }} · {{ \App\Models\Prisoner::CONDITIONS[$prisoner->condition] }} условия · ИИН {{ $prisoner->maskedIin() }}</p>
        <div class="limits-chips">
            @if($prisoner->needsReview())<span class="limits-chip limits-chip-warn">Зарегистрировался сам на планшете — проверьте ФИО, условия содержания и режим лимита (работает — «Без лимита»)</span>@endif
            <span class="limits-chip">{{ \App\Models\Prisoner::SPENDING_MODES[$prisoner->spending_mode ?? 'unlimited'] }}</span>
            @if($prisoner->isRegistered())
                <span class="limits-chip">Зарегистрирован {{ $prisoner->registered_at->timezone('Asia/Almaty')->format('d.m.Y') }} · {{ $prisoner->authMethod() === 'pin' ? 'пароль' : 'контрольный вопрос' }}</span>
                <span class="limits-chip">Дата рождения: {{ $prisoner->birth_date?->format('d.m.Y') ?? 'не указана' }} · восстановление: {{ ['birth_date' => 'по дате рождения', 'question' => 'по контрольному вопросу'][$prisoner->recoveryMethod()] ?? 'только через сотрудника' }}@if($prisoner->isRecoveryLocked()) · закрыто до {{ $prisoner->recovery_locked_until->timezone('Asia/Almaty')->format('d.m H:i') }} @endif @if($prisoner->recovered_at) · пароль восстановлен {{ $prisoner->recovered_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}@endif</span>
            @else
                <span class="limits-chip limits-chip-warn">Ещё не зарегистрирован на планшете</span>
            @endif
            @if($prisoner->familyCode())<span class="limits-chip">Код для родственников: {{ $prisoner->familyCode() }}</span>@endif
            @if($prisoner->isLocked())<span class="limits-chip limits-chip-warn">Вход заблокирован до {{ $prisoner->locked_until->timezone('Asia/Almaty')->format('H:i') }}</span>@endif
            @if($prisoner->last_login_at)<span class="limits-chip">Последний вход {{ $prisoner->last_login_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}</span>@endif
        </div>
    </div>
    <div class="limits-actions">
        @if($prisoner->needsReview())
            <form method="post" action="{{ route('admin.prisoners.review', $prisoner) }}">@csrf<button class="btn btn-primary">Проверено</button></form>
        @endif
        @if($prisoner->isLocked())
            <form method="post" action="{{ route('admin.prisoners.unlock', $prisoner) }}">@csrf<button class="btn">Снять блокировку</button></form>
        @endif
        @if($prisoner->registered_at)
            <form method="post" action="{{ route('admin.prisoners.reset-registration', $prisoner) }}" onsubmit="return confirm('Сбросить регистрацию? Пароль, дата рождения, контрольный вопрос и код для родственников будут удалены, человек зарегистрируется заново.')">@csrf<button class="btn">Сбросить регистрацию</button></form>
        @endif
        @if($prisoner->family_code_hash)
            <form method="post" action="{{ route('admin.prisoners.family-code.disable', $prisoner) }}">@csrf<button class="btn">Отключить код родственников</button></form>
        @endif
    </div>
</header>

<section class="limits-grid" aria-label="Баланс и лимиты">
    <div class="limits-metric limits-metric-main">
        <span>Баланс Dosmart</span>
        <strong>{{ $tenge($profile['balance_minor']) }}</strong>
        <small>Доступно для покупок на планшете</small>
    </div>
    <div class="limits-metric">
        <span>Лимит покупок в этом месяце</span>
        @if($limit === null)
            <strong>Без лимита</strong>
            <small>Потрачено за месяц: {{ $tenge($spent) }}</small>
        @else
            <strong>Осталось {{ $tenge($profile['monthly_remaining_minor']) }}</strong>
            <div class="limits-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}"><span style="width: {{ $percent }}%"></span></div>
            <small>Потрачено на товары {{ $tenge($spent) }} из {{ $tenge($available) }}: лимит {{ $tenge($limit) }}@if($profile['limit_carried_minor'] > 0) + перенос {{ $tenge($profile['limit_carried_minor']) }}@endif. Доставка в лимит не входит.@if($prisoner->spending_mode === 'norm' && $limit === 0) Норма учреждения не настроена.@endif</small>
        @endif
    </div>
    <div class="limits-metric">
        @if($profile['parcel_budget'])
            <span>Передачи от родственников, {{ $profile['parcel_budget']['period'] }}</span>
            <strong>{{ $profile['parcel_budget']['remaining_minor'] === null ? 'МРП не настроен' : 'Осталось '.$tenge($profile['parcel_budget']['remaining_minor']) }}</strong>
            <small>Норма: {{ isset($profile['parcel_budget']['norm_mrp']) ? $profile['parcel_budget']['norm_mrp'].' МРП в месяц' : 'на этот год не задана' }}</small>
        @else
            <span>Передачи от родственников</span>
            <strong>{{ $profile['parcels_available'] === null ? 'Без нормы' : 'Доступно: '.$profile['parcels_available'] }}</strong>
            <small>Нормы задаются на странице учреждения</small>
        @endif
    </div>
</section>

<div class="limits-columns">
    <section class="card limits-panel">
        <h2 class="limits-heading">Пополнить баланс</h2>
        <p class="limits-muted">Осуждённый подал заявление в администрации, деньги пришли на счёт магазина — внесите их сюда. Баланс увеличится сразу.</p>
        <form method="post" action="{{ route('admin.prisoners.deposit', $prisoner) }}" class="limits-form">
            @csrf
            <div class="limits-fields">
                <div class="form-group"><label>Сумма, ₸ <input class="form-control limits-amount" name="amount" type="number" step="0.01" min="0.01" max="9999999999.99" required value="{{ old('amount') }}" placeholder="50000"></label></div>
                <div class="form-group"><label>№ заявления <input class="form-control" name="application_number" maxlength="100" required value="{{ old('application_number') }}"></label></div>
                <div class="form-group"><label>Дата заявления <input class="form-control" name="application_date" type="date" required value="{{ old('application_date', now('Asia/Almaty')->toDateString()) }}"></label></div>
                <div class="form-group"><label>№ платежа / выписки <input class="form-control" name="payment_reference" maxlength="100" required value="{{ old('payment_reference') }}" placeholder="Из банковской выписки"></label></div>
            </div>
            <label class="limits-check"><input type="checkbox" name="payment_verified" value="1" required> Поступление на счёт магазина и сумма сверены с выпиской</label>
            <button class="btn btn-primary">Зачислить на баланс</button>
        </form>
        <details class="limits-more">
            <summary>Деньги ещё не пришли — только зарегистрировать заявление</summary>
            <form method="post" action="{{ route('admin.prisoners.transfers.store', $prisoner) }}" class="limits-form">
                @csrf
                <div class="limits-fields">
                    <div class="form-group"><label>№ заявления <input class="form-control" name="application_number" maxlength="100" required></label></div>
                    <div class="form-group"><label>Дата <input class="form-control" name="application_date" type="date" required value="{{ now('Asia/Almaty')->toDateString() }}"></label></div>
                    <div class="form-group"><label>Сумма, ₸ <input class="form-control" name="amount" type="number" step="0.01" min="0.01" max="9999999999.99" required></label></div>
                </div>
                <button class="btn btn-ghost">Зарегистрировать ожидание</button>
            </form>
        </details>
    </section>

    <section class="card limits-panel">
        <h2 class="limits-heading">Движение по балансу</h2>
        <ul class="limits-ledger">
            @forelse($history as $row)
                <li>
                    <span><strong>{{ $row['title'] }}</strong><small>{{ $row['at'] }}@if($row['status']) · {{ $row['status'] }}@endif</small></span>
                    <b class="{{ $row['amount_minor'] >= 0 ? 'limits-plus' : 'limits-minus' }}">{{ $row['amount_minor'] >= 0 ? '+' : '−' }}{{ $tenge(abs($row['amount_minor'])) }}</b>
                </li>
            @empty
                <li class="limits-empty">Операций пока нет.</li>
            @endforelse
        </ul>
    </section>
</div>

@if($depositRequests->isNotEmpty())
<section class="card limits-panel">
    <h2 class="limits-heading">Заявки родственников на пополнение</h2>
    @if($relativeTransfers['count'] >= 2 || ($relativeTransfers['cap_minor'] && $relativeTransfers['sum_minor'] >= $relativeTransfers['cap_minor']))
        <p class="limits-warn">В этом месяце уже зачислено переводов от родственников: {{ $relativeTransfers['count'] }} на {{ $tenge($relativeTransfers['sum_minor']) }}. Для учреждений средней и выше безопасности ст. 108 УИК допускает не более 2 переводов и 20 МРП в месяц.</p>
    @endif
    <ul class="limits-ledger">
        @foreach($depositRequests as $deposit)
            <li><span><strong>{{ $deposit->number() }} · {{ $deposit->sender_name }}</strong><small>{{ $deposit->created_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }} · {{ \App\Models\DepositRequest::STATUSES[$deposit->status] }}@if($deposit->status === 'new') · <a href="{{ route('admin.prisoners.requests') }}">обработать</a>@endif</small></span><b>{{ $tenge($deposit->amount_minor) }}</b></li>
        @endforeach
    </ul>
</section>
@endif

<section class="card limits-panel">
    <h2 class="limits-heading">Заявления на пополнение</h2>
    <div class="table-wrap"><table><thead><tr><th>№ и дата</th><th>Сумма</th><th>Статус</th><th>Кто и когда</th><th></th></tr></thead><tbody>
    @forelse($transfers as $transfer)
        @php([$label, $tone] = $statusBadge[$transfer->status])
        <tr>
            <td><strong>{{ $transfer->application_number }}</strong><br><small>{{ $transfer->application_date->format('d.m.Y') }}@if($transfer->sender_name) · от {{ $transfer->sender_name }}@endif</small>
                @if(in_array($transfer->status, ['confirmed', 'reversed'], true))<br><a href="{{ route('admin.prisoners.transfers.receipt', [$prisoner, $transfer]) }}" target="_blank" rel="noopener">Квитанция</a>@endif</td>
            <td>{{ $tenge($transfer->amount_minor) }}</td>
            <td><span class="badge badge-{{ $tone }}">{{ $label }}</span>@if($transfer->payment_reference)<br><small>Платёж {{ $transfer->payment_reference }}</small>@endif</td>
            <td><small>
                Внёс: {{ $staff[$transfer->registered_by] ?? '№'.$transfer->registered_by }}
                @if($transfer->confirmed_by)<br>Зачислил: {{ $staff[$transfer->confirmed_by] ?? '№'.$transfer->confirmed_by }}, {{ $transfer->confirmed_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}@endif
                @if($transfer->closed_by)<br>{{ $transfer->close_reason }} — {{ $staff[$transfer->closed_by] ?? '№'.$transfer->closed_by }}, {{ $transfer->closed_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}@endif
            </small></td>
            <td>
                @if(in_array($transfer->status, ['pending', 'confirmed'], true))
                <details class="limits-actions-menu"><summary class="btn btn-ghost btn-sm">Действия</summary>
                    @if($transfer->status === 'pending')
                        <form method="post" action="{{ route('admin.prisoners.transfers.settle', [$prisoner, $transfer]) }}" class="limits-form">
                            @csrf <input type="hidden" name="action" value="confirm">
                            <div class="form-group"><label>№ платежа <input class="form-control" name="payment_reference" maxlength="100" required></label></div>
                            <label class="limits-check"><input type="checkbox" name="payment_verified" value="1" required> Поступление сверено</label>
                            <button class="btn btn-primary btn-sm">Зачислить</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('admin.prisoners.transfers.settle', [$prisoner, $transfer]) }}" class="limits-form">
                        @csrf <input type="hidden" name="action" value="{{ $transfer->status === 'pending' ? 'reject' : 'reverse' }}">
                        <div class="form-group"><label>Причина <input class="form-control" name="reason" maxlength="255" required></label></div>
                        <button class="btn btn-danger-soft btn-sm">{{ $transfer->status === 'pending' ? 'Отклонить' : 'Сторнировать (списать обратно)' }}</button>
                    </form>
                </details>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="limits-empty">Заявлений пока нет.</td></tr>
    @endforelse
    </tbody></table></div>
    {{ $transfers->withQueryString()->links() }}
</section>

<section class="card limits-panel">
    <h2 class="limits-heading">Покупки и передачи</h2>
    <div class="table-wrap"><table><thead><tr><th>Заказ</th><th>Тип</th><th>Статус</th><th>Сумма</th></tr></thead><tbody>
        @forelse($orders as $order)
            <tr>
                <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a><br><small>{{ $order->created_at->timezone('Asia/Almaty')->format('d.m.Y H:i') }}</small></td>
                <td>{{ $order->prisoner_order_type === 'balance' ? 'Покупка с баланса' : 'Передача от родственника' }}</td>
                <td>{{ $order->status_label }}@if($order->parcel_state)<br><small>{{ ['reserved' => 'Резерв нормы', 'delivered' => 'Учтена '.$order->delivered_at?->timezone('Asia/Almaty')->format('d.m.Y'), 'released' => 'Резерв снят'][$order->parcel_state] }}</small>@endif</td>
                <td>{{ number_format($order->total, 2, ',', ' ') }} ₸
                    @if((float) $order->refund_amount > 0)<br><small>Возврат {{ number_format($order->refund_amount, 2, ',', ' ') }} ₸</small>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="limits-empty">Заказов пока нет.</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $orders->withQueryString()->links() }}
</section>

<section class="card limits-panel">
    <details @if($errors->hasAny(['full_name', 'squad', 'iin', 'condition', 'spending_mode', 'monthly_limit'])) open @endif>
        <summary class="limits-heading">Настройки карточки</summary>
        <form method="post" action="{{ route('admin.prisoners.update', $prisoner) }}" class="limits-form">
            @csrf @method('PATCH')
            <div class="form-group"><label>ФИО <input class="form-control" name="full_name" required maxlength="255" value="{{ old('full_name', $prisoner->full_name) }}"></label></div>
            <div class="limits-fields">
                <div class="form-group"><label>Отряд <input class="form-control" name="squad" maxlength="50" value="{{ old('squad', $prisoner->squad) }}"></label></div>
                <div class="form-group"><label>ИИН (необязательно) <input class="form-control" name="iin" pattern="[0-9]{12}" maxlength="12" inputmode="numeric" autocomplete="off" value="{{ old('iin', $prisoner->iin) }}"></label></div>
            </div>
            @include('admin.prisoners.condition')
            <button class="btn btn-primary">Сохранить</button>
        </form>
    </details>
    @if($prisoner->institution->requiresParcelCode())
        <div class="form-group" style="margin-top:16px"><label>Код для передач от родственников
            <input class="form-control" readonly value="{{ $prisoner->parcel_code }}" onclick="this.select()">
        </label><small class="limits-muted">Код сообщают родственнику для оформления передачи на сайте. На планшете человек входит по ФИО, отряду и своему паролю.</small></div>
    @endif
</section>
</div>
@endsection
