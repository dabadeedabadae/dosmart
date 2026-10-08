<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Institution;
use App\Models\Order;
use App\Models\OrderDraft;
use App\Models\Product;
use App\Models\User;
use App\Services\PilotCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PilotTest extends TestCase
{
    use RefreshDatabase;

    private function draft(string $source = 'website'): OrderDraft
    {
        $product = Product::create(['name' => 'Чай', 'price' => 1250]);

        return app(PilotCheckout::class)->create(['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2]]], $source);
    }

    private function details(): array
    {
        $institution = Institution::create(['name' => 'Учреждение 1', 'city' => 'Алматы', 'is_active' => true]);

        return ['prisoner_name' => 'Тестовый Получатель', 'institution_id' => $institution->id, 'contact_phone' => '+77001112233', 'delivery_type' => 'urgent', 'consent' => 1, 'expected_total' => 7500];
    }

    public function test_terminal_auth_random_code_and_idempotency(): void
    {
        config(['pilot.terminal_token' => str_repeat('s', 40), 'app.url' => 'https://dosmart.kz']);
        $product = Product::create(['name' => 'Чай', 'price' => 1250]);
        $payload = ['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1]]];
        $this->postJson('/api/v1/drafts', $payload)->assertUnauthorized();
        $response = $this->withToken(str_repeat('s', 40))->postJson('/api/v1/drafts', $payload)->assertCreated()->assertJsonPath('subtotal', 2500);
        $code = $response->json('code');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $code);
        $this->assertSame('https://dosmart.kz/o/'.$code, $response->json('url'));
        $this->assertStringContainsString('Код: '.$code, $response->json('chat_text'));
        $this->withToken(str_repeat('s', 40))->postJson('/api/v1/drafts', $payload)->assertJsonPath('code', $code);
        $this->assertSame(1, OrderDraft::count());
        $this->assertSame(1, DB::table('pilot_events')->count());
        $payload['request_id'] = (string) Str::uuid();
        $second = $this->withToken(str_repeat('s', 40))->postJson('/api/v1/drafts', $payload)->assertCreated();
        $this->assertNotSame($code, $second->json('code'));
    }

    public function test_guest_checkout_consent_fee_idempotency_and_payment_privacy(): void
    {
        $draft = $this->draft();
        $data = $this->details();
        $this->get('/o/'.$draft->code)->assertOk()->assertSee('Чай');
        $invalid = $data;
        unset($invalid['consent']);
        $this->post('/o/'.$draft->code, $invalid)->assertSessionHasErrors('consent');
        $data['delivery_fee'] = 1;
        $data['total'] = 1;
        $this->post('/o/'.$draft->code, $data)->assertRedirect('/o/'.$draft->code.'/payment');
        $order = Order::first();
        $this->assertSame('7500.00', $order->total);
        $this->assertSame('5000.00', $order->delivery_fee);
        $this->assertNotNull($order->consented_at);
        $this->assertNull($order->customer_id);
        $this->post('/o/'.$draft->code, $data)->assertRedirect();
        $this->assertSame(1, Order::count());
        $this->get('/o/'.$draft->code.'/payment')->assertOk()->assertSee($order->order_number)->assertDontSee($data['contact_phone'])->assertDontSee($data['prisoner_name'])->assertSee('Ссылка Kaspi пока не настроена');
        $this->post('/o', ['code' => strtolower($draft->code)])->assertRedirect('/o/'.$draft->code);
    }

    public function test_reported_payment_is_idempotent_unpaid_and_status_tracks_admin_changes(): void
    {
        config(['pilot.kaspi_url' => 'https://pay.kaspi.kz/pay/example']);
        $draft = $this->draft();
        $data = $this->details();
        $this->getJson('/o/'.$draft->code.'/status')->assertNotFound();
        $this->post('/o/'.$draft->code.'/payment-reported')->assertNotFound();
        $this->post('/o/'.$draft->code, $data)->assertRedirect();
        $order = Order::first();
        $statusUrl = '/o/'.$draft->code.'/status';
        $before = $this->getJson($statusUrl)->assertOk()->assertJsonPath('label', 'Ожидает оплаты')->json('revision');
        $this->get('/o/'.$draft->code.'/payment')->assertSee('Оплатить через Kaspi')->assertSee('Я оплатил');
        $this->post('/o/'.$draft->code.'/payment-reported', ['status' => 'paid', 'total' => 1])->assertRedirect();
        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertNotSame('paid', $order->payment_status);
        $this->assertNull($order->paid_at);
        $this->assertSame('7500.00', $order->total);
        $this->assertNotNull($order->payment_reported_at);
        $count = $order->statusHistory()->count();
        $this->post('/o/'.$draft->code.'/payment-reported')->assertRedirect();
        $this->assertSame($count, $order->statusHistory()->count());
        $response = $this->getJson($statusUrl)->assertOk()->assertJsonPath('label', 'Заказ в обработке');
        $this->assertNotSame($before, $response->json('revision'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertDontSee($data['contact_phone'])->assertDontSee($data['prisoner_name']);
        $this->get('/o/'.$draft->code.'/payment')->assertSee('Заказ в обработке')->assertDontSee('Оплатить через Kaspi');
        $admin = User::factory()->create(['username' => 'payment_operator']);
        $this->actingAs($admin, 'web')->get('/admin/orders')->assertSee('Клиент сообщил об оплате');
        $url = '/admin/orders/'.$order->id.'/status';
        $this->patch($url, ['status' => 'paid'])->assertSessionHasErrors('payment_verified');
        $this->patch($url, ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'REAL-CHECK-TEST'])->assertRedirect();
        $this->getJson($statusUrl)->assertJsonPath('status', 'paid')->assertJsonPath('label', 'Оплачен');
        $this->patch($url, ['status' => 'delivering'])->assertRedirect();
        $this->getJson($statusUrl)->assertJsonPath('label', 'В доставке');
        $this->patch($url, ['status' => 'delivered'])->assertRedirect();
        $this->getJson($statusUrl)->assertJsonPath('label', 'Доставлен');
        $this->post('/o/'.$draft->code.'/payment-reported')->assertRedirect();
        $this->assertSame('delivered', $order->fresh()->status);
        $this->getJson('/o/ZZZZZZZZ/status')->assertNotFound();
    }

    public function test_standard_delivery_account_ownership_and_stale_prices(): void
    {
        $draft = $this->draft();
        $data = $this->details();
        $data['delivery_type'] = 'standard';
        $data['expected_total'] = 5500;
        $customer = Customer::create(['phone' => '+77002223344', 'password' => 'password123']);
        $this->actingAs($customer, 'customer')->post('/o/'.$draft->code, $data)->assertRedirect();
        $order = Order::first();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('5500.00', $order->total);
        $this->get('/shop/orders')->assertOk()->assertSee($order->order_number);
        $other = Customer::create(['phone' => '+77009999999', 'password' => 'password123']);
        $this->actingAs($other, 'customer')->get('/shop/orders')->assertDontSee($order->order_number);
        $this->getJson('/api/v1/orders/'.$order->order_number)->assertNotFound();
        $draft2 = $this->draft();
        Product::whereKey($draft2->items[0]['product_id'])->update(['price' => 1300]);
        $this->post('/o/'.$draft2->code, $data)->assertSessionHasErrors('items');
        $this->assertSame(1, Order::count());
    }

    public function test_expiry_pruning_and_unavailable_product(): void
    {
        $draft = $this->draft();
        $draft->update(['expires_at' => now()->subMinute()]);
        $this->get('/o/'.$draft->code)->assertGone();
        $this->post('/o/'.$draft->code, $this->details())->assertGone();
        $this->artisan('drafts:prune')->assertSuccessful();
        $this->assertSame(0, OrderDraft::count());
        $this->assertSame(1, DB::table('pilot_events')->count());
        $draft = $this->draft();
        Product::whereKey($draft->items[0]['product_id'])->update(['in_stock' => false]);
        $this->get('/o/'.$draft->code)->assertConflict();
        $this->post('/o/'.$draft->code, $this->details())->assertSessionHasErrors('items');
    }

    public function test_admin_manual_verification_delivery_and_partial_refund(): void
    {
        $draft = $this->draft('terminal');
        $this->post('/o/'.$draft->code, $this->details())->assertRedirect();
        $order = Order::first();
        $admin = User::factory()->create(['username' => 'operator']);
        $this->actingAs($admin, 'web')->get('/admin/orders')->assertOk()->assertSee('Заявки с терминала');
        $this->get('/admin/orders/'.$order->id)->assertOk();
        $url = '/admin/orders/'.$order->id.'/status';
        $this->patch($url, ['status' => 'delivering'])->assertSessionHasErrors('status');
        $this->patch($url, ['status' => 'paid'])->assertSessionHasErrors('payment_verified');
        $this->patch($url, ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'TEST-123'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->patch($url, ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'TEST-123'])->assertRedirect();
        $this->assertSame(1, DB::table('pilot_events')->where('event', 'payment_confirmed')->count());
        $this->patch($url, ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->patch($url, ['status' => 'delivering'])->assertRedirect();
        $this->patch($url, ['status' => 'delivered'])->assertRedirect();
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->patch($url, ['status' => 'refunded', 'refund_amount' => 8000, 'refund_verified' => 1, 'comment' => 'Не принято'])->assertSessionHasErrors('refund_amount');
        $this->patch($url, ['status' => 'refunded', 'refund_amount' => 1000, 'refund_verified' => 1, 'comment' => 'Частичный возврат'])->assertRedirect();
        $this->assertSame('1000.00', (string) $order->fresh()->refund_amount);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->patch($url, ['status' => 'refunded', 'refund_amount' => 7500, 'refund_verified' => 1, 'comment' => 'Полный возврат'])->assertRedirect();
        $this->assertSame('7500.00', (string) $order->fresh()->refund_amount);
    }

    public function test_request_id_conflict_terminal_fail_closed_and_staff_login(): void
    {
        config(['pilot.terminal_token' => '']);
        $this->withToken('anything')->postJson('/api/v1/drafts', [])->assertUnauthorized();
        $draft = $this->draft();
        $this->postJson('/shop/drafts', ['request_id' => $draft->request_id, 'items' => [['product_id' => $draft->items[0]['product_id'], 'quantity' => 3]]])->assertConflict();
        $admin = User::factory()->create(['username' => 'staff_login', 'password' => 'test-password-123']);
        $this->post('/admin/login', ['username' => 'staff_login', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/admin/login', ['username' => 'staff_login', 'password' => 'test-password-123'])->assertRedirect('/admin/orders');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertGuest('customer');
        $this->get('/admin/login')->assertRedirect('/admin/orders');
    }
}
