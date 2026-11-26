<details class="card limits-panel" open>
    <summary>Месячные нормы покупок со счёта (МРП)</summary>
    @php($mrpNow = now('Asia/Almaty'))
    @if(! isset($institution->parcel_mrp_rates[$mrpNow->year]) && ! config('pilot.mrp.'.$mrpNow->year))
        <p class="limits-warn">МРП на {{ $mrpNow->year }} год не введён — лимиты считаются по МРП прошлого года. Введите новый МРП ниже.</p>
    @elseif($mrpNow->month === 12 && ! isset($institution->parcel_mrp_rates[$mrpNow->year + 1]) && ! config('pilot.mrp.'.($mrpNow->year + 1)))
        <p class="limits-warn">Скоро новый год: введите МРП на {{ $mrpNow->year + 1 }} год. Пока его нет, с 1 января лимиты будут считаться по МРП {{ $mrpNow->year }} года.</p>
    @endif
    <p class="limits-muted" style="margin-top:8px">Используются у карточек с режимом «Норма учреждения». По ст. 105 УИК РК лимит не действует на деньги, заработанные в учреждении, пенсии и пособия, а также для беременных, женщин с детьми, лиц с инвалидностью и находящихся в лечебных учреждениях — таким людям ставьте «Без лимита». Неизрасходованный остаток переносится на следующие месяцы (ч. 2 ст. 105).
        Нормы УИК: средняя безопасность (ст. 136) — строгие 2, обычные 6, облегчённые 12, льготные 15 МРП; максимальная (ст. 138) — строгие 2, обычные 5, облегчённые 10, льготные 12 МРП. В минимальной безопасности (ст. 143) деньгами пользуются без ограничения — режим «Без лимита».</p>
    @php($currentYear = now('Asia/Almaty')->year)
    <form method="post" action="{{ route('admin.institutions.spending-limits', $institution) }}" class="limits-form" style="max-width:640px">
        @csrf @method('PATCH')
        <div class="limits-fields">
            <div class="form-group"><label>Год <input class="form-control" name="mrp_year" type="number" min="2000" max="2100" required value="{{ $currentYear }}"></label></div>
            <div class="form-group"><label>1 МРП, ₸ <input class="form-control" name="mrp_value" type="number" min="1" max="1000000" required value="{{ $institution->parcel_mrp_rates[$currentYear] ?? config('pilot.mrp.'.$currentYear) }}"></label></div>
        </div>
        <div class="limits-fields">
            @foreach(\App\Models\Prisoner::CONDITIONS as $value => $label)
                @php($norm = $institution->spending_limits_mrp[$currentYear][$value] ?? null)
                <div class="form-group"><label>{{ $label }}, МРП
                    <input class="form-control" name="spend_{{ $value }}" type="number" min="0" max="9999" value="{{ $norm }}" placeholder="не задано">
                </label>@if($norm !== null && isset($institution->parcel_mrp_rates[$currentYear]))<small class="limits-muted">= {{ number_format($norm * $institution->parcel_mrp_rates[$currentYear], 0, ',', ' ') }} ₸ в месяц</small>@endif</div>
            @endforeach
        </div>
        <button class="btn btn-primary">Сохранить нормы покупок</button>
    </form>
</details>
