<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Order;
use App\Models\Prisoner;
use App\Models\PrisonerLimitPeriod;
use App\Models\User;
use App\Services\PrisonerAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrisonerLimitCarryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time, 'Asia/Almaty'));
    }

    private function person(array $attributes = []): Prisoner
    {
        $institution = Institution::first() ?? Institution::create(['name' => 'ИК-1', 'city' => 'Петропавловск', 'is_active' => true, 'order_code' => '01']);
        $prisoner = Prisoner::create(array_diff_key($attributes, ['limit_started_on' => 1]) + [
            'full_name' => 'Тестов Арман', 'institution_id' => $institution->id, 'condition' => 'ordinary',
            'spending_mode' => 'custom', 'monthly_limit_minor' => 1_000_000, 'parcel_code' => strtoupper(Str::random(24)),
        ]);
        $prisoner->forceFill(['limit_started_on' => $attributes['limit_started_on'] ?? '2026-11-01'])->save();

        return $prisoner->fresh('institution');
    }

    private function purchase(Prisoner $prisoner, string $at, float $goods, float $refund = 0, bool $withDelivery = false): Order
    {
        $order = $prisoner->orders()->make([
            'order_number' => 'DOS-'.Str::uuid(), 'institution_id' => $prisoner->institution_id, 'institution_name' => 'ИК-1',
            'prisoner_name' => $prisoner->full_name, 'contact_phone' => '', 'subtotal' => $goods, 'delivery_fee' => 3000,
            'total' => $goods + 3000, 'refund_amount' => $refund, 'refund_includes_delivery' => $withDelivery,
            'delivery_type' => 'standard', 'status' => 'paid', 'payment_status' => 'paid', 'prisoner_order_type' => 'balance',
        ]);
        $order->created_at = Carbon::parse($at, 'Asia/Almaty')->utc();
        $order->save();

        return $order;
    }

    private function state(Prisoner $prisoner): array
    {
        return app(PrisonerAccounts::class)->limitState($prisoner->fresh('institution'));
    }

    public function test_refund_counts_only_goods_against_limit(): void
    {
        $this->at('2026-11-20 12:00');
        $prisoner = $this->person();
        $this->purchase($prisoner, '2026-11-05 12:00', 8000, 3000, true);   // вернули только доставку
        $this->purchase($prisoner, '2026-11-06 12:00', 1000, 400);          // вернули часть товаров
        $this->purchase($prisoner, '2026-11-07 12:00', 500, 3500);          // вернули весь заказ

        $this->assertSame(800_000 + 60_000, $this->state($prisoner)['spent']);
    }

    public function test_admin_marks_delivery_in_refund_and_balance_and_limit_are_right(): void
    {
        $this->at('2026-11-20 12:00');
        $prisoner = $this->person();
        $prisoner->forceFill(['balance_minor' => 0])->save();
        $order = $this->purchase($prisoner, '2026-11-10 12:00', 8000);
        $this->actingAs(User::factory()->create());

        $this->patch(route('admin.orders.status', $order), [
            'status' => 'refunded', 'refund_amount' => 2000, 'comment' => 'Нельзя вернуть меньше доставки',
            'refund_verified' => '1', 'refund_includes_delivery' => '1',
        ])->assertSessionHasErrors('refund_amount');

        $this->patch(route('admin.orders.status', $order), [
            'status' => 'refunded', 'refund_amount' => 3000, 'comment' => 'Доставка не состоялась',
            'refund_verified' => '1', 'refund_includes_delivery' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->refund_includes_delivery);
        $this->assertSame(300_000, $prisoner->fresh()->balance_minor);
        $this->assertSame(800_000, $this->state($prisoner)['spent']);

        // Второй возврат: ещё 1 000 за товары. Отметка о доставке сохраняется.
        $this->patch(route('admin.orders.status', $order), [
            'status' => 'refunded', 'refund_amount' => 4000, 'comment' => 'Не было товара', 'refund_verified' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->refund_includes_delivery);
        $this->assertSame(400_000, $prisoner->fresh()->balance_minor);
        $this->assertSame(700_000, $this->state($prisoner)['spent']);
    }

    public function test_unsaved_past_month_uses_norm_of_that_month(): void
    {
        $prisoner = $this->person(['spending_mode' => 'norm', 'monthly_limit_minor' => null, 'limit_started_on' => '2026-12-01']);
        $prisoner->institution->forceFill([
            'parcel_mrp_rates' => [2026 => 4325, 2027 => 5000],
            'spending_limits_mrp' => [2026 => ['ordinary' => 6], 2027 => ['ordinary' => 6]],
        ])->save();

        // Декабрь никто не открывал: лимит декабря — по МРП 2026 года, а не по январскому.
        $this->at('2027-01-10 12:00');
        $state = $this->state($prisoner);
        $this->assertSame(6 * 5000 * 100, $state['base']);
        $this->assertSame(6 * 4325 * 100, $state['carried']);
    }

    public function test_saved_month_keeps_its_limit_after_change(): void
    {
        $prisoner = $this->person();
        $this->at('2026-11-15 12:00');
        Artisan::call('limits:snapshot');

        $this->at('2026-12-05 12:00');
        $prisoner->forceFill(['monthly_limit_minor' => 500_000])->save();
        $state = $this->state($prisoner);
        $this->assertSame(1_000_000, $state['carried']);
        $this->assertSame(500_000, $state['base']);
    }

    public function test_new_year_without_new_mrp_uses_last_known_instead_of_zero(): void
    {
        $prisoner = $this->person(['spending_mode' => 'norm', 'monthly_limit_minor' => null, 'limit_started_on' => '2027-01-01']);
        $prisoner->institution->forceFill(['parcel_mrp_rates' => [2026 => 4325], 'spending_limits_mrp' => [2026 => ['ordinary' => 6]]])->save();
        config(['pilot.mrp' => [2026 => 4325]]);

        $this->at('2027-01-02 12:00');
        $this->assertSame(6 * 4325 * 100, $this->state($prisoner)['remaining']);
        $this->assertFalse(app(PrisonerAccounts::class)->normMissing($prisoner->fresh('institution')));
    }

    public function test_norm_not_configured_is_reported_clearly(): void
    {
        $this->at('2026-11-20 12:00');
        $prisoner = $this->person(['spending_mode' => 'norm', 'monthly_limit_minor' => null]);
        $this->assertTrue(app(PrisonerAccounts::class)->normMissing($prisoner));
        $this->assertSame(0, $this->state($prisoner)['remaining']);
    }

    public function test_snapshot_command_saves_only_limited_people(): void
    {
        $this->at('2026-11-20 12:00');
        $limited = $this->person();
        $this->person(['full_name' => 'Примеров Данияр', 'spending_mode' => 'unlimited', 'monthly_limit_minor' => null]);

        $this->assertSame(0, Artisan::call('limits:snapshot'));
        $this->assertSame(1, PrisonerLimitPeriod::count());
        $this->assertSame(1_000_000, $limited->limitPeriods()->where('period', '2026-11')->value('limit_minor'));
    }
}
