<details class="card limits-panel" open>
    <summary>Передачи от родственников — количество в год (по закону)</summary>
    <p class="limits-muted" style="margin-top:8px">Заказ, который оплачивает родственник, — это передача. По УИК РК передачи ограничены количеством в год.
        Средняя безопасность (ст. 136): обычные — 6, облегчённые — 12, льготные — 12, строгие — 3.
        Максимальная (ст. 138): обычные — 4, облегчённые — 6, льготные — 6, строгие — 2.
        Когда нормы заданы, заказ сверх нормы на сайте не оформится.</p>
    <form method="post" action="{{ route('admin.institutions.parcel-limits', $institution) }}" style="margin-top:12px;max-width:500px">
        @csrf @method('PATCH')<input type="hidden" name="mode" value="count">
        @foreach(\App\Models\Prisoner::CONDITIONS as $value => $label)
            <div class="form-group"><label>{{ $label }} · передач в год
                <input class="form-control" name="{{ $value }}" type="number" min="0" max="9999" @if($value !== 'privileged') required @endif value="{{ old($value, $institution->parcel_limits[$value] ?? '') }}">
            </label></div>
        @endforeach
        <button class="btn btn-primary">Сохранить нормы передач</button>
    </form>
</details>
<details class="card limits-panel">
    <summary>Нормы передач в МРП в месяц (только по согласованию с учреждением)</summary>
    <p class="limits-muted" style="margin-top:8px">Внимание: по УИК РК передачи ограничены количеством в год, а не суммой. Сумму в МРП включайте только по письменному согласованию с учреждением. Сохранение этой формы заменит нормы «в год».</p>
    @php($currentYear = now('Asia/Almaty')->year)
    <form method="post" action="{{ route('admin.institutions.parcel-limits', $institution) }}" style="margin-top:12px;max-width:500px">
        @csrf @method('PATCH')
        <div class="form-group"><label>Год <input class="form-control" name="mrp_year" type="number" min="2000" max="2100" required value="{{ old('mrp_year', $currentYear) }}"></label></div>
        <div class="form-group"><label>Размер одного МРП, ₸ <input class="form-control" name="mrp_value" type="number" min="1" max="1000000" required value="{{ old('mrp_value', $institution->parcel_mrp_rates[$currentYear] ?? '') }}"></label></div>
        @foreach(\App\Models\Prisoner::CONDITIONS as $value => $label)
            <div class="form-group"><label>{{ $label }} · МРП
                <input class="form-control" name="{{ $value }}" type="number" min="0" max="9999" required value="{{ old($value, $institution->parcel_limits_mrp[$currentYear][$value] ?? '') }}">
            </label></div>
        @endforeach
        <button class="btn btn-primary">Сохранить нормы</button>
    </form>
</details>
