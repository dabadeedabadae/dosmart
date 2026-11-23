@php($mode = old('spending_mode', $prisoner->spending_mode ?? 'unlimited'))
<div class="limits-fields">
    <div class="form-group"><label>Условия содержания
        <select name="condition" class="form-control" required>
            @foreach(\App\Models\Prisoner::CONDITIONS as $value => $label)
                <option value="{{ $value }}" @selected(old('condition', $prisoner->condition ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label></div>
    <div class="form-group"><label>Лимит покупок с баланса
        <select name="spending_mode" class="form-control" required data-spending-mode>
            @foreach(\App\Models\Prisoner::SPENDING_MODES as $value => $label)
                <option value="{{ $value }}" @selected($mode === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label></div>
    <div class="form-group" data-custom-limit @if($mode !== 'custom') hidden @endif><label>Сумма в месяц, ₸
        <input type="number" name="monthly_limit" class="form-control" min="0" max="9999999999.99" step="0.01" value="{{ old('monthly_limit', isset($prisoner->monthly_limit_minor) ? $prisoner->monthly_limit_minor / 100 : '') }}">
    </label></div>
</div>
<p class="limits-hint">«Без лимита» — например, работающий осуждённый тратит заработанное. «Норма учреждения» — месячная норма в МРП для его условий содержания (настраивается на странице учреждения). «Своя сумма» — лимит вручную.</p>
@once
@push('scripts')
<script>
document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-spending-mode]')) return;
    const box = event.target.closest('form').querySelector('[data-custom-limit]');
    box.hidden = event.target.value !== 'custom';
    box.querySelector('input').required = event.target.value === 'custom';
});
</script>
@endpush
@endonce
