<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TerminalSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        config(['pilot.terminal_identity_url' => 'https://identity.example/api/user']);
        Http::fake(['identity.example/*' => fn ($r) => Http::response([
            'id' => $r->hasHeader('Authorization', 'Bearer other') ? 8 : 7,
            'id_number' => '123', 'first_name' => 'Имя из профиля',
        ])]);
        $product = Product::create(['name' => 'Чай', 'price' => 800]);

        return ['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'prisoner_name' => 'Иванов Иван Иванович', 'institution_name' => 'Учреждение №12',
            'contact_phone' => '8 (701) 123-45-67', 'consent' => true];
    }

    public function test_submission_saves_manual_fields_and_retries_are_isolated_and_idempotent(): void
    {
        $data = $this->payload();
        $response = $this->withToken('session')->postJson('/api/v1/terminal/orders', $data)->assertCreated()->assertJsonPath('status', 'new');
        $this->assertArrayNotHasKey('url', $response->json());
        $order = Order::firstOrFail();
        $this->assertSame($data['prisoner_name'], $order->prisoner_name);
        $this->assertSame($data['institution_name'], $order->institution_name);
        $this->assertSame('+77011234567', $order->contact_phone);
        $this->assertSame('1600.00', $order->subtotal);
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('unconfirmed', $order->delivery_type);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNotNull($order->consented_at);
        $this->assertSame(2, $order->items->first()->quantity);
        Product::query()->update(['price' => 999, 'in_stock' => false]);
        $this->withToken('session')->postJson('/api/v1/terminal/orders', $data)->assertCreated()->assertJsonPath('order_number', $order->order_number)->assertJsonPath('subtotal', '1600.00');
        $changed = $data;
        $changed['contact_phone'] = '+77017654321';
        $this->withToken('session')->postJson('/api/v1/terminal/orders', $changed)->assertConflict();
        $this->withToken('other')->postJson('/api/v1/terminal/orders', $data)->assertConflict();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('pilot_events', 1);
    }

    public function test_required_fields_consent_and_availability_are_checked_without_partial_orders(): void
    {
        $data = $this->payload();
        foreach (['prisoner_name', 'institution_name', 'consent'] as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->withToken('session')->postJson('/api/v1/terminal/orders', $invalid)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $invalid = $data;
        $invalid['contact_phone'] = 'abc123';
        $this->withToken('session')->postJson('/api/v1/terminal/orders', $invalid)->assertUnprocessable();
        Product::query()->update(['in_stock' => false]);
        $this->withToken('session')->postJson('/api/v1/terminal/orders', $data)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_drafts', 0);
    }

    public function test_terminal_order_can_be_submitted_without_relative_phone(): void
    {
        $data = $this->payload();
        unset($data['contact_phone']);
        $this->withToken('session')->postJson('/api/v1/terminal/orders', $data)->assertCreated();
        $this->assertSame('', Order::first()->contact_phone);
        $this->withToken('session')->postJson('/api/v1/terminal/orders', $data)->assertCreated();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_admin_agrees_delivery_before_payment_and_verifies_payment_manually(): void
    {
        $data = $this->payload();
        config(['pilot.kaspi_url' => 'https://pay.kaspi.kz/pay/test']);
        $this->withToken('session')->postJson('/api/v1/terminal/orders', $data)->assertCreated();
        $order = Order::firstOrFail();
        $payment = '/o/'.$order->draft->code.'/payment';
        $this->get($payment)->assertOk()->assertSee('Пока оплачивать не нужно')->assertDontSee('Оплатить через Kaspi')->assertDontSee($data['prisoner_name'])->assertDontSee('+77011234567');
        $this->get('/admin/orders/'.$order->id)->assertRedirect('/admin/login');
        $admin = User::factory()->create(['username' => 'operator']);
        $this->actingAs($admin, 'web')->get('/admin/orders?status=new')->assertOk()->assertSee($data['institution_name'])->assertSee('Новая заявка');
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('WhatsApp родственника')->assertSee('Открыть WhatsApp');
        $url = '/admin/orders/'.$order->id.'/status';
        $this->patch($url, ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'id'])->assertSessionHasErrors('status');
        $this->patch($url, ['status' => 'pending'])->assertSessionHasErrors('delivery_type');
        $this->patch($url, ['status' => 'pending', 'delivery_type' => 'urgent'])->assertRedirect();
        $this->assertSame('6600.00', $order->fresh()->total);
        $this->get($payment)->assertOk()->assertSee('Оплатить через Kaspi');
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Ссылка на оплату для родственника');
        $this->patch($url, ['status' => 'paid'])->assertSessionHasErrors('payment_verified');
        $this->patch($url, ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'TEST-1'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseHas('pilot_events', ['event' => 'payment_confirmed']);
    }

    public function test_received_requests_survive_draft_cleanup_and_can_be_cancelled_or_priced(): void
    {
        $data = $this->payload();
        $this->withToken('session')->postJson('/api/v1/terminal/orders', $data)->assertCreated();
        $order = Order::firstOrFail();
        $this->travel(6)->days();
        $this->artisan('drafts:prune')->assertSuccessful();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_drafts', 1);
        $this->actingAs(User::factory()->create(), 'web');
        $this->patch('/admin/orders/'.$order->id.'/status', ['status' => 'pending', 'delivery_type' => 'standard'])->assertRedirect();
        $this->assertSame('4600.00', $order->fresh()->total);
        $this->patch('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])->assertRedirect();
        $this->get('/o/'.$order->draft->code.'/payment')->assertOk()->assertSee('Не оплачивайте')->assertDontSee('Оплатить через Kaspi');
    }

    public function test_new_submission_requires_a_verified_session(): void
    {
        $data = $this->payload();
        config(['pilot.terminal_identity_url' => '']);
        $this->postJson('/api/v1/terminal/orders', $data)->assertStatus(503);
        config(['pilot.terminal_identity_url' => 'https://identity.example/api/user']);
        $this->postJson('/api/v1/terminal/orders', $data)->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }
}
