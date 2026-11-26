<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Order;
use App\Models\Prisoner;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrisonerAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function institution(): Institution
    {
        return Institution::create(['name' => 'Тестовое учреждение', 'city' => 'Алматы', 'is_active' => true, 'order_code' => '01']);
    }

    private function prisoner(Institution $institution, array $attributes = []): Prisoner
    {
        return Prisoner::create($attributes + [
            'iin' => '000000000001', 'full_name' => 'Тестовый Получатель', 'squad' => '7',
            'institution_id' => $institution->id, 'condition' => 'ordinary',
            'parcel_code' => strtoupper(bin2hex(random_bytes(12))),
        ]);
    }

    private function login(Prisoner $prisoner): string
    {
        $secret = str_repeat('g', 40);
        config(['pilot.prisoner_gateway_secret' => $secret]);
        $identity = ['full_name' => 'тестовый получ', 'squad' => $prisoner->squad];
        $this->postJson('/api/v1/prisoner/find', $identity)->assertForbidden();
        $tablet = $this->withHeader('X-Dosmart-Tablet-Gateway', $secret)
            ->postJson('/api/v1/tablet/setup', ['code' => $prisoner->institution->issueTabletCode()])->assertOk()->json('token');

        return $this->withHeader('X-Dosmart-Tablet', $tablet)->postJson('/api/v1/prisoner/register', $identity + ['consent' => true,
            'prisoner_id' => $prisoner->id, 'birth_date' => '1990-01-01', 'pin' => '482915', 'pin_confirmation' => '482915',
        ])->assertOk()->assertJsonMissingPath('profile.iin')->assertJsonMissingPath('profile.parcel_code')->json('token');
    }

    private function purchaseData(int $productId): array
    {
        return [
            'request_id' => (string) Str::uuid(), 'items' => [['product_id' => $productId, 'quantity' => 1]],
            'squad_number' => '7', 'expected_total_minor' => 400050, 'consent' => true,
        ];
    }

    public function test_staff_registers_paper_application_and_only_a_verified_unique_transfer_credits_the_balance(): void
    {
        $institution = $this->institution();
        $this->get('/admin/prisoners')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create());
        $this->get('/admin/prisoners')->assertOk();
        $this->post('/admin/prisoners', [
            'iin' => '000000000001', 'full_name' => 'Тестовый Получатель', 'squad' => '7',
            'institution_id' => $institution->id, 'condition' => 'ordinary', 'spending_mode' => 'unlimited',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $prisoner = Prisoner::firstOrFail();
        $base = '/admin/prisoners/'.$prisoner->id;
        $this->get($base)->assertOk()->assertSee('Без лимита');
        $application = ['application_number' => 'Вх-001', 'application_date' => now('Asia/Almaty')->toDateString(), 'amount' => '10000.25'];
        $this->post($base.'/transfers', $application)->assertSessionHasNoErrors();
        $this->post($base.'/transfers', $application)->assertSessionHasErrors('application_number');
        $transfer = $prisoner->transfers()->firstOrFail();
        $this->assertSame(0, $prisoner->fresh()->balance_minor);
        $settle = $base.'/transfers/'.$transfer->id;
        $confirm = ['action' => 'confirm', 'payment_reference' => 'BANK-001'];
        $this->post($settle, $confirm)->assertSessionHasErrors('payment_verified');
        $confirm['payment_verified'] = '1';
        $this->post($settle, $confirm)->assertSessionHasNoErrors();
        $this->post($settle, $confirm)->assertSessionHasNoErrors();
        $this->assertSame(1000025, $prisoner->fresh()->balance_minor);

        $this->post($base.'/transfers', ['application_number' => 'Вх-002'] + $application)->assertSessionHasNoErrors();
        $second = $prisoner->transfers()->latest('id')->firstOrFail();
        $this->post($base.'/transfers/'.$second->id, $confirm)->assertSessionHasErrors('payment_reference');
        $this->assertSame(1000025, $prisoner->fresh()->balance_minor);
        $this->get($base)->assertOk()->assertSee('Зачислено')->assertSee('Ожидает перевода');

        $this->post($settle, ['action' => 'reverse', 'reason' => 'Ошибка сверки'])->assertSessionHasNoErrors();
        $this->post($settle, ['action' => 'reverse', 'reason' => 'Ошибка сверки'])->assertSessionHasNoErrors();
        $this->assertSame(0, $prisoner->fresh()->balance_minor);
    }

    public function test_balance_purchase_checks_total_and_retries_safely_with_optional_monthly_limit_and_refund(): void
    {
        $this->travelTo(Carbon::parse('2026-11-30 23:50', 'Asia/Almaty'));
        $prisoner = $this->prisoner($this->institution());
        $prisoner->forceFill(['balance_minor' => 1000000])->save();
        $this->actingAs(User::factory()->create());
        $token = $this->login($prisoner);
        $product = Product::create(['name' => 'Чай', 'price' => '1000.50']);
        $data = $this->purchaseData($product->id);
        $path = '/api/v1/prisoner/purchases';
        $this->postJson($path, $data)->assertUnauthorized();
        $this->withToken($token)->postJson($path, ['expected_total_minor' => 100050] + $data)
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $first = $this->withToken($token)->postJson($path, $data)->assertCreated()->assertJsonPath('total_minor', 400050)->json('order_number');
        $this->withToken($token)->postJson($path, $data)->assertCreated()->assertJsonPath('order_number', $first);
        $this->withToken($token)->postJson($path, ['squad_number' => '8'] + $data)->assertConflict();
        $this->assertSame(599950, $prisoner->fresh()->balance_minor);
        $order = Order::firstOrFail();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNull($order->parcel_state);
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Покупка со счёта Dosmart');

        // Лимит считается только по товарам: доставка 3 000 ₸ в него не входит.
        $prisoner->forceFill(['spending_mode' => 'custom', 'monthly_limit_minor' => 200000, 'limit_started_on' => '2026-11-01'])->save();
        $this->withToken($token)->getJson('/api/v1/prisoner/profile')->assertJsonPath('monthly_remaining_minor', 99950)
            ->assertJsonPath('monthly_spent_minor', 100050)->assertJsonPath('limit_carried_minor', 0);
        $next = $this->purchaseData($product->id);
        $this->withToken($token)->postJson($path, $next)->assertUnprocessable()->assertJsonValidationErrors('balance');
        // Неиспользованный остаток ноября переносится на декабрь.
        $this->travelTo(Carbon::parse('2026-12-01 00:00', 'Asia/Almaty'));
        $this->withToken($token)->getJson('/api/v1/prisoner/profile')->assertJsonPath('monthly_remaining_minor', 299950)
            ->assertJsonPath('limit_carried_minor', 99950)->assertJsonPath('monthly_limit_minor', 200000);
        $this->withToken($token)->postJson($path, $next)->assertCreated();
        $this->withToken($token)->postJson($path, $this->purchaseData($product->id))->assertUnprocessable()
            ->assertJsonPath('errors.balance.0', 'На счёте недостаточно денег.');

        $refund = ['status' => 'refunded', 'refund_amount' => '4000.50', 'refund_verified' => '1', 'comment' => 'Тестовый возврат'];
        $this->patch('/admin/orders/'.$order->id.'/status', $refund)->assertSessionHasNoErrors();
        $this->patch('/admin/orders/'.$order->id.'/status', $refund)->assertSessionHasErrors('refund_amount');
        $this->assertSame(599950, $prisoner->fresh()->balance_minor);
        $this->withToken($token)->getJson('/api/v1/prisoner/profile')->assertJsonPath('limit_carried_minor', 200000)
            ->assertJsonPath('monthly_remaining_minor', 299950);
        $this->withoutHeader('X-Dosmart-Tablet-Gateway')->withToken($token)->getJson('/api/v1/prisoner/profile')->assertForbidden();
    }

    public function test_parcels_use_institution_and_condition_rules_and_follow_cancel_handover_and_year_boundary(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31 23:50', 'Asia/Almaty'));
        $this->actingAs(User::factory()->create());
        $institution = $this->institution();
        $this->patch('/admin/institutions/'.$institution->id.'/parcel-limits', ['ordinary' => 1, 'eased' => 2, 'strict' => 0])
            ->assertSessionHasNoErrors();
        $prisoner = $this->prisoner($institution);
        // У получателя есть кабинет — лимит передач проверяется.
        $prisoner->forceFill(['balance_minor' => 1000000, 'registered_at' => now()])->save();
        $product = Product::create(['name' => 'Чай', 'price' => '1000.50']);
        $items = [['product_id' => $product->id, 'quantity' => 1]];
        $checkout = [
            'prisoner_name' => 'Тестовый Получатель', 'institution_id' => $institution->id,
            'relative_name' => 'Отправитель Тестовый', 'contact_phone' => '+7 705 621 8748', 'delivery_type' => 'standard', 'consent' => 1, 'expected_total' => '4000.50',
        ];
        $draft = fn () => '/o/'.$this->postJson('/api/v1/guest/drafts', [
            'request_id' => (string) Str::uuid(), 'items' => $items,
        ])->assertCreated()->json('code');

        $first = $draft();
        $this->get($first)->assertOk()->assertDontSee('Код получателя')->assertDontSee('Дата рождения получателя');
        // Человека нет в списке учреждения — заказ не оформить.
        $this->post($first, ['prisoner_name' => 'Несуществующий Человек Тестович'] + $checkout)->assertSessionHasErrors('prisoner_name');
        $this->assertDatabaseCount('orders', 0);
        $this->post($first, $checkout)->assertRedirect($first.'/payment')->assertSessionHasNoErrors();
        $this->post($first, $checkout)->assertRedirect($first.'/payment');
        $order = Order::firstOrFail();
        $this->assertSame('reserved', $order->parcel_state);
        $second = $draft();
        $this->post($second, $checkout)->assertSessionHasErrors('prisoner_name');

        $this->patch('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('released', $order->fresh()->parcel_state);
        $this->post($second, $checkout)->assertRedirect($second.'/payment')->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->travelTo(Carbon::parse('2027-01-01 00:00', 'Asia/Almaty'));
        $third = $draft();
        $this->post($third, $checkout)->assertSessionHasErrors('prisoner_name');

        $status = '/admin/orders/'.$order->id.'/status';
        $this->patch($status, ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'KASPI-001'])->assertSessionHasNoErrors();
        $this->patch($status, ['status' => 'delivering'])->assertSessionHasNoErrors();
        $this->patch($status, ['status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $order->fresh()->parcel_state);
        $this->assertEquals(2027, $order->fresh()->parcel_year);
        $this->post($third, $checkout)->assertSessionHasErrors('prisoner_name');
        $prisoner->update(['condition' => 'eased']);
        $this->post($third, $checkout)->assertRedirect($third.'/payment')->assertSessionHasNoErrors();
        $prisoner->update(['condition' => 'strict']);
        $this->post($draft(), $checkout)->assertSessionHasErrors('prisoner_name');
        $this->assertSame(1000000, $prisoner->fresh()->balance_minor);
    }

    public function test_terminal_request_requires_a_parcel_reservation_before_opening_kaspi(): void
    {
        $institution = $this->institution();
        $institution->forceFill(['parcel_limits' => ['ordinary' => 1, 'eased' => 2, 'strict' => 0]])->save();
        $prisoner = $this->prisoner($institution);
        $prisoner->forceFill(['registered_at' => now()])->save();
        $product = Product::create(['name' => 'Чай', 'price' => '1000.50']);
        $this->postJson('/api/v1/guest/orders', [
            'request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'prisoner_name' => $prisoner->full_name, 'institution_id' => $institution->id,
            'relative_name' => 'Отправитель Тестовый', 'contact_phone' => '+77056218748', 'consent' => 1,
        ])->assertCreated();

        $this->actingAs(User::factory()->create());
        $order = Order::firstOrFail();
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertDontSee('Код получателя');
        $path = '/admin/orders/'.$order->id.'/status';
        // Получатель находится по ФИО в списке учреждения — код не нужен.
        $this->patch($path, ['status' => 'pending', 'delivery_type' => 'standard'])->assertSessionHasNoErrors();
        $this->assertSame('reserved', $order->fresh()->parcel_state);
        $this->patch($path, ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'KASPI-001'])->assertSessionHasNoErrors();
        $refund = ['status' => 'refunded', 'refund_verified' => '1', 'comment' => 'До вручения'];
        $this->patch($path, ['refund_amount' => '1000'] + $refund)->assertSessionHasNoErrors();
        $this->assertSame('reserved', $order->fresh()->parcel_state);
        $this->patch($path, ['refund_amount' => '4000.50'] + $refund)->assertSessionHasNoErrors();
        $this->assertSame('released', $order->fresh()->parcel_state);
        $this->assertSame(0, $prisoner->fresh()->balance_minor);
    }

    public function test_institutions_filter_people_and_open_a_separate_create_form(): void
    {
        $institution = $this->institution();
        $prisoner = $this->prisoner($institution);
        $other = Institution::create(['name' => 'Другое учреждение', 'city' => 'Алматы']);
        $this->prisoner($other, ['iin' => '000000000002', 'full_name' => 'Другой Получатель', 'condition' => 'strict']);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/prisoners')->assertOk()->assertSee('Все учреждения')->assertSee('Другое учреждение')
            ->assertDontSee($prisoner->full_name);
        $this->get('/admin/prisoners/people')->assertOk()->assertSee('Другой Получатель')->assertSee('Добавить человека');
        $this->get('/admin/prisoners/people?institution_id='.$institution->id)->assertOk()
            ->assertSee($prisoner->full_name)->assertDontSee('Другой Получатель')->assertDontSee('name="iin"', false);
        $this->get('/admin/prisoners/people?condition=strict')->assertOk()
            ->assertSee('Другой Получатель')->assertDontSee($prisoner->full_name);
        $this->get('/admin/prisoners/create?institution_id='.$institution->id)->assertOk()
            ->assertSee('name="iin"', false)->assertSee('value="'.$institution->id.'" selected', false);
    }

    public function test_monthly_mrp_budget_reserves_goods_only_and_rejects_an_increase_above_the_limit(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 17:00', 'Asia/Almaty'));
        $this->actingAs(User::factory()->create());
        $institution = $this->institution();
        $prisoner = $this->prisoner($institution);
        $url = '/admin/institutions/'.$institution->id.'/parcel-limits';
        $this->patch($url, ['mrp_year' => 2026, 'mrp_value' => 1000, 'ordinary' => 2, 'eased' => 5, 'strict' => 0])
            ->assertSessionHasNoErrors();
        $this->patch($url, ['mrp_year' => 2027, 'mrp_value' => 1500, 'ordinary' => 3, 'eased' => 7, 'strict' => 1])
            ->assertSessionHasNoErrors();
        $product = Product::create(['name' => 'Чай', 'price' => '1250.50']);
        $checkout = [
            'prisoner_name' => $prisoner->full_name, 'institution_id' => $institution->id,
            'relative_name' => 'Отправитель Тестовый', 'contact_phone' => '+7 705 621 8748', 'delivery_type' => 'standard', 'consent' => 1,
            'expected_total' => '4250.50',
        ];
        $draft = fn () => '/o/'.$this->postJson('/api/v1/guest/drafts', [
            'request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('code');

        $first = $draft();
        $lookup = ['institution_id' => $institution->id, 'prisoner_name' => mb_strtoupper($prisoner->full_name)];
        $this->postJson($first.'/recipient-limits', ['prisoner_name' => 'Нет Такого Человека'] + $lookup)->assertOk()
            ->assertExactJson(['limited' => true, 'found' => false, 'namesakes' => false]);
        // Родственник видит только остаток лимита.
        $this->postJson($first.'/recipient-limits', $lookup)->assertOk()
            ->assertExactJson(['limited' => true, 'found' => true, 'parcel_budget_minor' => 200000, 'parcels_available' => null]);
        $this->post($first, $checkout)->assertRedirect($first.'/payment')->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->get('/admin/prisoners/'.$prisoner->id)->assertOk()->assertSee('2 МРП')->assertSee('749,50');
        $second = $draft();
        $this->post($second, $checkout)->assertSessionHasErrors('prisoner_name');

        $this->post($first.'/changes', [
            'revision' => app(OrderChanges::class)->revision($order),
            'items' => [['id' => $order->items()->firstOrFail()->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();
        $change = $order->changeRequests()->firstOrFail();
        $this->post('/admin/orders/'.$order->id.'/changes/'.$change->id, ['decision' => 'approved'])
            ->assertSessionHasErrors('changes');
        $this->assertSame('1250.50', $order->fresh()->subtotal);

        $this->post('/admin/orders/'.$order->id.'/changes/'.$change->id, ['decision' => 'rejected'])
            ->assertSessionHasNoErrors();
        $status = '/admin/orders/'.$order->id.'/status';
        $this->patch($status, ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'TEST-MRP-001'])->assertSessionHasNoErrors();
        $this->patch($status, ['status' => 'delivering'])->assertSessionHasNoErrors();
        $this->patch($status, ['status' => 'delivered'])->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-12-01 00:00', 'Asia/Almaty'));
        $this->get('/admin/prisoners/'.$prisoner->id)->assertOk()->assertSee('2 МРП')->assertSee('2 000,00');
        $this->post($draft(), $checkout)->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2027-01-01 00:00', 'Asia/Almaty'));
        $this->get('/admin/prisoners/'.$prisoner->id)->assertOk()->assertSee('3 МРП')->assertSee('3 249,50');
    }
}
