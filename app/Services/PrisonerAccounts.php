<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Order;
use App\Models\Prisoner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrisonerAccounts
{
    public static function minor(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public function profile(Prisoner $prisoner): array
    {
        $limit = $this->limitState($prisoner);

        return [
            'full_name' => $prisoner->full_name,
            'institution' => $prisoner->institution->name,
            'squad' => $prisoner->squad,
            'condition' => Prisoner::CONDITIONS[$prisoner->condition] ?? $prisoner->condition,
            'condition_norm_mrp' => $this->conditionNorm($prisoner),
            // Новая карточка ещё не перечитана из базы — счёт по умолчанию 0, а не null.
            'balance_minor' => (int) $prisoner->balance_minor,
            'spending_mode' => $prisoner->spending_mode ?? 'unlimited',
            'monthly_limit_minor' => $limit['base'] ?? null,
            'limit_carried_minor' => $limit['carried'] ?? 0,
            'monthly_spent_minor' => $limit['spent'] ?? $this->monthlySpent($prisoner),
            'monthly_remaining_minor' => $limit['remaining'] ?? null,
            'parcels_available' => $this->parcelsAvailable($prisoner),
            'parcel_budget' => $this->parcelBudget($prisoner),
            'delivery_minor' => self::minor(config('pilot.delivery_fee')),
            'auth_method' => $prisoner->authMethod(),
            'recovery_method' => $prisoner->recoveryMethod(),
            'recovery_question' => $prisoner->recoveryMethod() === 'question'
                ? (Prisoner::SECURITY_QUESTIONS[$prisoner->security_question] ?? null) : null,
            'birth_date_set' => $prisoner->birth_date !== null,
            'reviewed' => ! $prisoner->needsReview(),
            'family_code_active' => $prisoner->family_code_hash !== null,
        ];
    }

    /** Только лимиты — для родственника, который сам собирает корзину на сайте. Счёт не показываем. */
    public function limitsOnly(Prisoner $prisoner): array
    {
        $limit = $this->limitState($prisoner);

        return [
            'monthly_limit_minor' => $limit['available'] ?? null,
            'monthly_remaining_minor' => $limit['remaining'] ?? null,
            'parcels_available' => $this->parcelsAvailable($prisoner),
            'parcel_budget_minor' => $this->parcelBudget($prisoner)['remaining_minor'] ?? null,
        ];
    }

    /** Профиль для самого осуждённого на планшете: плюс код для родственников. */
    public function ownProfile(Prisoner $prisoner): array
    {
        return $this->profile($prisoner) + ['family_code' => $prisoner->familyCode()];
    }

    /**
     * Месячный лимит расходования в тиынах; null — без лимита.
     * «Норма»: норма учреждения в МРП для условий содержания × размер МРП текущего года.
     * Если норма выбрана, но не настроена, лимит 0 — покупка сверх нормы не пройдёт.
     */
    public function monthlyLimit(Prisoner $prisoner, ?Carbon $when = null): ?int
    {
        $mode = $prisoner->spending_mode ?? ($prisoner->monthly_limit_minor === null ? 'unlimited' : 'custom');
        if ($mode === 'custom') {
            return $prisoner->monthly_limit_minor ?? 0;
        }
        if ($mode !== 'norm') {
            return null;
        }
        $year = ($when ?? now())->copy()->timezone('Asia/Almaty')->year;
        $institution = $prisoner->institution;
        $norm = self::forYear($institution->spending_limits_mrp ?? [], $year)[$prisoner->condition] ?? null;
        $rate = self::mrpRate($institution, $year);

        return $norm === null || $rate === null ? 0 : (int) $norm * (int) $rate * 100;
    }

    /**
     * Размер МРП на год: из настроек учреждения, из config/pilot.php или, если год ещё не введён,
     * последний известный — чтобы 1 января лимиты не обнулялись, пока сотрудник не ввёл новый МРП.
     */
    public static function mrpRate(Institution $institution, int $year): ?int
    {
        $rates = ($institution->parcel_mrp_rates ?? []) + (array) config('pilot.mrp', []);
        $rate = self::forYear($rates, $year);

        return $rate === null ? null : (int) $rate;
    }

    /** Значение на год или на последний более ранний год, если на этот ещё не задано. */
    /** Норма покупок учреждения (ст. 105 УИК) для условий человека в текущем году, МРП в месяц. */
    public function conditionNorm(Prisoner $prisoner): ?int
    {
        $norm = self::forYear($prisoner->institution?->spending_limits_mrp ?? [], now('Asia/Almaty')->year)[$prisoner->condition] ?? null;

        return $norm === null ? null : (int) $norm;
    }

    /** «Облегчённые (12 МРП)» — условия и норма рядом. */
    public function conditionLabel(Prisoner $prisoner): string
    {
        $label = Prisoner::CONDITIONS[$prisoner->condition] ?? (string) $prisoner->condition;
        $norm = $this->conditionNorm($prisoner);

        return $norm === null ? $label : $label.' ('.$norm.' МРП)';
    }

    private static function forYear(array $byYear, int $year): mixed
    {
        if (array_key_exists($year, $byYear)) {
            return $byYear[$year];
        }
        $earlier = array_filter(array_keys($byYear), fn ($y) => (int) $y < $year);

        return $earlier ? $byYear[max($earlier)] : null;
    }

    /** Норма учреждения в МРП для условий содержания не задана — лимит 0 по ошибке настройки, а не по закону. */
    public function normMissing(Prisoner $prisoner, ?Carbon $when = null): bool
    {
        if ($prisoner->spending_mode !== 'norm') {
            return false;
        }
        $year = ($when ?? now())->copy()->timezone('Asia/Almaty')->year;

        return (self::forYear($prisoner->institution->spending_limits_mrp ?? [], $year)[$prisoner->condition] ?? null) === null
            || self::mrpRate($prisoner->institution, $year) === null;
    }

    /** Потрачено на товары со счёта за месяц. Доставка — отдельная услуга и в лимит не входит. */
    public function monthlySpent(Prisoner $prisoner, ?Carbon $when = null): int
    {
        $start = ($when ?? now())->copy()->timezone('Asia/Almaty')->startOfMonth();

        return $this->spentByMonth($prisoner, $start, $start->copy()->addMonth())[$start->format('Y-m')] ?? 0;
    }

    /** @return array<string, int> «ГГГГ-ММ» => потрачено на товары, тиыны. */
    private function spentByMonth(Prisoner $prisoner, Carbon $from, Carbon $to): array
    {
        $spent = [];
        $orders = $prisoner->orders()->where('prisoner_order_type', 'balance')
            ->where('created_at', '>=', $from->copy()->utc())->where('created_at', '<', $to->copy()->utc())
            ->get(['created_at', 'subtotal', 'total', 'refund_amount', 'refund_includes_delivery', 'delivery_fee']);
        foreach ($orders as $order) {
            $month = $order->created_at->copy()->timezone('Asia/Almaty')->format('Y-m');
            $spent[$month] = ($spent[$month] ?? 0) + self::goodsSpent($order);
        }

        return $spent;
    }

    /**
     * Сколько по заказу потрачено на товары (для лимита). Из возврата вычитаем доставку,
     * если сотрудник отметил, что она вошла в возврат, или если вернули весь заказ.
     */
    public static function goodsSpent(Order $order): int
    {
        $goods = self::minor($order->subtotal);
        $refund = self::minor($order->refund_amount ?? 0);
        $delivery = self::minor($order->delivery_fee ?? 0);
        $deliveryBack = $order->refund_includes_delivery || $refund >= self::minor($order->total) ? $delivery : 0;

        return max(0, $goods - max(0, $refund - $deliveryBack));
    }

    /**
     * Лимит месяца с переносом: неиспользованный остаток прошлых месяцев добавляется
     * к лимиту следующего (ч. 2 ст. 105 УИК). Перенос считается с месяца включения лимита.
     *
     * @return array{base:int, carried:int, available:int, spent:int, remaining:int}|null
     */
    public function limitState(Prisoner $prisoner, ?Carbon $when = null): ?array
    {
        $month = ($when ?? now())->copy()->timezone('Asia/Almaty')->startOfMonth();
        $base = $this->monthlyLimit($prisoner, $month);
        if ($base === null) {
            return null;
        }
        $period = $month->format('Y-m');
        $snapshot = $prisoner->limitPeriods()->firstOrNew(['period' => $period]);
        if (! $snapshot->exists || $snapshot->limit_minor !== $base) {
            $snapshot->limit_minor = $base;
            $snapshot->save();
        }

        $start = $prisoner->limit_started_on
            ? Carbon::parse($prisoner->limit_started_on->format('Y-m-01'), 'Asia/Almaty')
            : $month->copy();
        $start = $start->max($month->copy()->subMonths(24))->min($month);
        $limits = $prisoner->limitPeriods()->where('period', '>=', $start->format('Y-m'))->pluck('limit_minor', 'period');
        $spent = $this->spentByMonth($prisoner, $start, $month->copy()->addMonth());

        $carried = 0;
        for ($cursor = $start->copy(); $cursor->lt($month); $cursor->addMonth()) {
            $key = $cursor->format('Y-m');
            // Если месяц не был сохранён (никто не открывал кабинет), берём лимит по нормам того месяца.
            $limit = $limits[$key] ?? $this->monthlyLimit($prisoner, $cursor) ?? 0;
            $carried = max(0, (int) $limit + $carried - ($spent[$key] ?? 0));
        }
        $used = $spent[$period] ?? 0;

        return [
            'base' => $base, 'carried' => $carried, 'available' => $base + $carried,
            'spent' => $used, 'remaining' => max(0, $base + $carried - $used),
        ];
    }

    public function monthlyRemaining(Prisoner $prisoner, ?Carbon $when = null): ?int
    {
        return $this->limitState($prisoner, $when)['remaining'] ?? null;
    }

    /** Движение по счёту: зачисления, сторно, покупки и возвраты — новые сверху. */
    public function history(Prisoner $prisoner, int $limit = 50): array
    {
        $rows = collect();
        $transfers = $prisoner->transfers()->whereIn('status', ['confirmed', 'reversed'])->latest('id')->limit($limit)->get();
        foreach ($transfers as $transfer) {
            $rows->push(['kind' => 'deposit', 'title' => $transfer->source === 'relative'
                ? 'Пополнение от родственника'.($transfer->sender_name ? ': '.$transfer->sender_name : '')
                : 'Пополнение, заявление № '.$transfer->application_number,
                'amount_minor' => $transfer->amount_minor, 'at' => $transfer->confirmed_at]);
            if ($transfer->status === 'reversed') {
                $rows->push(['kind' => 'reversal', 'title' => 'Отмена пополнения № '.$transfer->application_number,
                    'amount_minor' => -$transfer->amount_minor, 'at' => $transfer->closed_at]);
            }
        }
        foreach ($prisoner->orders()->where('prisoner_order_type', 'balance')->latest('id')->limit($limit)->get() as $order) {
            $rows->push(['kind' => 'purchase', 'title' => 'Покупка № '.$order->order_number, 'status' => $order->status_label,
                'amount_minor' => -self::minor($order->total), 'at' => $order->created_at]);
            if ((float) $order->refund_amount > 0) {
                $rows->push(['kind' => 'refund', 'title' => 'Возврат по покупке № '.$order->order_number,
                    'amount_minor' => self::minor($order->refund_amount), 'at' => $order->updated_at]);
            }
        }

        return $rows->filter(fn ($row) => $row['at'] !== null)
            ->sortByDesc(fn ($row) => $row['at']->getTimestamp())->take($limit)
            ->map(fn ($row) => ['status' => null, ...$row, 'at' => $row['at']->copy()->timezone('Asia/Almaty')->format('d.m.Y H:i')])
            ->values()->all();
    }

    public function parcelsAvailable(Prisoner $prisoner): ?int
    {
        $limits = $prisoner->institution->parcel_limits;
        if ($limits === null) {
            return null;
        }
        $year = now('Asia/Almaty')->startOfYear();

        // Нормы, сохранённые до появления льготных условий, их не содержат — значит, не ограничено.
        if (! isset($limits[$prisoner->condition])) {
            return null;
        }

        return max(0, $limits[$prisoner->condition] - $this->countedParcels($prisoner, $year, $year->copy()->addYear())->count());
    }

    private function countedParcels(Prisoner $prisoner, Carbon $start, Carbon $end)
    {
        // Open reservations continue to count until handover or release.
        return $prisoner->orders()->where(function ($query) use ($start, $end) {
            $query->where('parcel_state', 'reserved')->orWhere(function ($query) use ($start, $end) {
                $query->where('parcel_state', 'delivered')
                    ->where('delivered_at', '>=', $start->copy()->utc())
                    ->where('delivered_at', '<', $end->copy()->utc());
            });
        });
    }

    public function parcelBudget(Prisoner $prisoner, ?int $exceptOrder = null): ?array
    {
        $institution = $prisoner->institution;
        if ($institution->parcel_limits_mrp === null) {
            return null;
        }
        $month = now('Asia/Almaty')->startOfMonth();
        $norm = $institution->parcel_limits_mrp[$month->year][$prisoner->condition] ?? null;
        $rate = $institution->parcel_mrp_rates[$month->year] ?? null;
        $used = $this->countedParcels($prisoner, $month, $month->copy()->addMonth())
            ->when($exceptOrder, fn ($query, $id) => $query->whereKeyNot($id))->sum('subtotal');

        return [
            'period' => $month->format('m.Y'), 'norm_mrp' => $norm, 'rate_tenge' => $rate,
            'remaining_minor' => $rate === null || $norm === null ? null : max(0, $norm * $rate * 100 - self::minor($used)),
        ];
    }

    private function assertParcelBudget(Prisoner $prisoner, int $amount, string $field, ?int $exceptOrder = null): void
    {
        $budget = $this->parcelBudget($prisoner, $exceptOrder);
        if ($budget === null) {
            return;
        }
        if ($budget['remaining_minor'] === null) {
            throw ValidationException::withMessages([$field => 'Размер МРП на текущий год не настроен. Обратитесь к сотруднику магазина.']);
        }
        if ($budget['norm_mrp'] === 0 || $amount > $budget['remaining_minor']) {
            throw ValidationException::withMessages([$field => 'Сумма товаров больше лимита получателя на этот месяц. Уберите лишнее из корзины.']);
        }
    }

    public function checkParcelIncrease(Order $order, int $newSubtotal): void
    {
        if ($order->parcel_state !== 'reserved' || $newSubtotal <= self::minor($order->subtotal)) {
            return;
        }

        $institution = Institution::whereKey($order->institution_id)->lockForUpdate()->firstOrFail();
        $prisoner = Prisoner::whereKey($order->prisoner_id)->lockForUpdate()->firstOrFail();
        $prisoner->setRelation('institution', $institution);
        $this->assertParcelBudget($prisoner, $newSubtotal, 'changes', $order->id);
    }

    /**
     * Получатель передачи по точным ФИО в учреждении (список учреждения или регистрация на планшете).
     * Возвращает null, если не найден; коллекцию из 2+ — если полные тёзки.
     */
    public function recipientByName(int $institutionId, string $name)
    {
        $name = Prisoner::normalizeName($name);
        if (mb_strlen($name) < 3) {
            return collect();
        }

        return Prisoner::where('institution_id', $institutionId)->get(['id', 'full_name'])
            ->filter(fn (Prisoner $p) => Prisoner::normalizeName($p->full_name) === $name)->values();
    }

    /** Остаток для родственника на сайте: только лимит, ничего больше. */
    public function recipientLimit(Prisoner $prisoner): array
    {
        return [
            'parcel_budget_minor' => $this->parcelBudget($prisoner)['remaining_minor'] ?? null,
            'parcels_available' => $this->parcelsAvailable($prisoner),
        ];
    }

    // Called inside the order transaction while holding the order/draft lock.
    // Если у учреждения заданы нормы передач, получатель должен быть в списке учреждения, а корзина — в пределах лимита.
    public function reserveParcel(Order $order, Institution $institution): void
    {
        if ($order->prisoner_order_type !== null || ! $institution->requiresParcelCode()) {
            return;
        }
        $found = $this->recipientByName($institution->id, (string) $order->prisoner_name);
        if ($found->isEmpty()) {
            throw ValidationException::withMessages(['prisoner_name' => 'Такого человека нет в списке учреждения. Проверьте ФИО — полностью, как в документах.']);
        }
        if ($found->count() > 1) {
            throw ValidationException::withMessages(['prisoner_name' => 'В учреждении несколько человек с такими ФИО. Напишите нам в WhatsApp — оформим заказ вручную.']);
        }
        $prisoner = Prisoner::whereKey($found->first()->id)->lockForUpdate()->firstOrFail();

        $this->reserveParcelFor($order, $institution, $prisoner);
    }

    /** Заказ через родственника для известного получателя: сверх нормы передач — блок. */
    public function reserveParcelFor(Order $order, Institution $institution, Prisoner $prisoner): void
    {
        if ($order->prisoner_order_type !== null || ! $institution->requiresParcelCode()) {
            return;
        }
        $prisoner = Prisoner::whereKey($prisoner->id)->lockForUpdate()->firstOrFail();
        $prisoner->setRelation('institution', $institution);
        if ($this->parcelsAvailable($prisoner) === 0) {
            throw ValidationException::withMessages(['prisoner_name' => 'У получателя закончились передачи на этот год. Заказ оформить нельзя.']);
        }

        $this->assertParcelBudget($prisoner, self::minor($order->subtotal), 'prisoner_name');

        $order->update([
            'prisoner_id' => $prisoner->id,
            'prisoner_name' => $prisoner->full_name,
            'prisoner_order_type' => 'parcel',
            'parcel_state' => 'reserved',
        ]);
    }

    public function purchase(Prisoner $profile, array $data, PilotCheckout $checkout): Order
    {
        $items = collect($data['items'])->map(fn ($item) => [
            'product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity'],
        ])->sortBy('product_id')->values()->all();
        $hash = hash('sha256', json_encode([$items, (int) $data['expected_total_minor'], $data['squad_number']], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($profile, $data, $items, $hash, $checkout) {
            $institution = Institution::whereKey($profile->institution_id)->lockForUpdate()->firstOrFail();
            $prisoner = Prisoner::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $prisoner->institution_id === (int) $profile->institution_id && $institution->is_active, 401);
            if ($prisoner->needsReview()) {
                throw ValidationException::withMessages(['balance' => 'Сотрудник магазина ещё не проверил вашу регистрацию: условия содержания и лимит. Покупка со счёта станет доступна после проверки.']);
            }
            $prisoner->setRelation('institution', $institution);
            $existing = $prisoner->orders()->where('purchase_request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->purchase_hash, $hash), 409, 'Для изменённой корзины создайте новую попытку.');

                return $existing;
            }

            $quote = $checkout->quote($items);
            $fee = self::minor(config('pilot.delivery_fee'));
            $total = self::minor($quote['subtotal']) + $fee;
            if ($total <= 0 || $total !== (int) $data['expected_total_minor']) {
                throw ValidationException::withMessages(['items' => 'Сумма изменилась. Обновите корзину и проверьте итог.']);
            }
            if ($total > $prisoner->balance_minor) {
                throw ValidationException::withMessages(['balance' => 'На счёте недостаточно денег.']);
            }
            $purchasedAt = now();
            $remaining = $this->monthlyRemaining($prisoner, $purchasedAt);
            if ($remaining !== null && $this->normMissing($prisoner, $purchasedAt)) {
                throw ValidationException::withMessages(['balance' => 'Норма покупок для вашего учреждения ещё не настроена. Обратитесь к сотруднику магазина.']);
            }
            if ($remaining !== null && self::minor($quote['subtotal']) > $remaining) {
                throw ValidationException::withMessages(['balance' => 'Товары превышают остаток месячного лимита. Доставка в лимит не входит.']);
            }

            $order = $prisoner->orders()->make([
                'order_number' => 'DOS-'.Str::uuid(),
                'display_number' => app(OrderNumbers::class)->next($prisoner->institution_id),
                'institution_id' => $prisoner->institution_id,
                'institution_name' => $prisoner->institution->name,
                'prisoner_name' => $prisoner->full_name, 'contact_phone' => '',
                'squad_number' => $data['squad_number'],
                'subtotal' => $quote['subtotal'], 'delivery_fee' => $fee / 100, 'total' => $total / 100,
                'delivery_type' => 'standard', 'status' => 'paid', 'payment_status' => 'paid',
                'paid_at' => $purchasedAt, 'consented_at' => $purchasedAt, 'consent_version' => config('pilot.consent_version'),
                'prisoner_order_type' => 'balance', 'purchase_request_id' => $data['request_id'], 'purchase_hash' => $hash,
            ]);
            $order->created_at = $purchasedAt;
            $order->save();
            $order->items()->createMany($quote['items']);
            $order->statusHistory()->create(['status' => 'paid', 'comment' => 'Оплачено со счёта Dosmart.']);
            $prisoner->decrement('balance_minor', $total);

            return $order;
        }, 3);
    }

    // The caller holds the order lock. Lock the same profile as reservation and purchase.
    public function applyOrderStatus(Order $order, string $next, int $refundMinor): void
    {
        if (! $order->prisoner_id) {
            return;
        }
        $prisoner = Prisoner::whereKey($order->prisoner_id)->lockForUpdate()->firstOrFail();
        if ($order->prisoner_order_type === 'balance' && $next === 'refunded') {
            $prisoner->increment('balance_minor', $refundMinor - self::minor($order->refund_amount));
        }
        if ($order->parcel_state === 'reserved' && $next === 'delivered') {
            $order->update(['parcel_state' => 'delivered', 'parcel_year' => now('Asia/Almaty')->year]);
        }
        if ($order->parcel_state === 'reserved' && ($next === 'cancelled'
            || ($next === 'refunded' && $refundMinor === self::minor($order->total)))) {
            $order->update(['parcel_state' => 'released']);
        }
    }
}
