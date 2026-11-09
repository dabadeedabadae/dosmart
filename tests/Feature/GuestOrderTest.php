<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestOrderTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $p = Product::create(['name' => 'Чай', 'price' => 800]);
        $i = Institution::create(['name' => 'Учреждение №1', 'city' => 'Алматы', 'is_active' => true]);
        return ['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $p->id, 'quantity' => 2]],
            'prisoner_name' => 'Иванов Иван Иванович', 'squad_number' => '7', 'institution_id' => $i->id,
            'contact_phone' => '8 (701) 123-45-67', 'consent' => true];
    }

    public function test_guest_request_is_unpaid_server_priced_and_idempotent(): void
    {
        $data = $this->payload();
        $data['total'] = 1;
        $data['status'] = 'paid';
        $data['institution_name'] = 'Подмена';
        $r = $this->postJson('/api/v1/guest/orders', $data)->assertCreated()->assertJsonPath('status', 'new');
        $o = Order::firstOrFail();
        $this->assertSame('1600.00', $o->subtotal);
        $this->assertSame('unpaid', $o->payment_status);
        $this->assertSame('7', $o->squad_number);
        $this->assertSame('unconfirmed', $o->delivery_type);
        $this->assertSame('Учреждение №1', $o->institution_name);
        $this->assertSame('+77011234567', $o->contact_phone);
        $this->assertSame('website', $o->draft->source);
        // SQLite does not enforce varchar lengths; production PostgreSQL does.
        $this->assertLessThanOrEqual(64, strlen($o->draft->terminal_subject));
        $this->assertArrayNotHasKey('contact_phone', $r->json());
        Product::query()->update(['in_stock' => false]);
        $this->postJson('/api/v1/guest/orders', $data)->assertCreated()->assertJsonPath('order_number', $o->order_number);
        $data['contact_phone'] = '+77017654321';
        $this->postJson('/api/v1/guest/orders', $data)->assertConflict();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_readable_number_payment_message_and_legacy_client_compatibility(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-09 12:00:00', 'Asia/Almaty'));
        config(['pilot.kaspi_url' => 'https://pay.kaspi.kz/pay/8nohzgrr']);
        $data = $this->payload();
        Institution::whereKey($data['institution_id'])->update(['order_code' => '07']);
        $response = $this->postJson('/api/v1/guest/orders', $data)->assertCreated();
        $order = Order::firstOrFail();
        $this->assertMatchesRegularExpression('/^0710[0-9]{3}$/', $order->order_number);
        $this->assertMatchesRegularExpression('/^DOS-[A-Z2-9]{8}$/', $response->json('order_number'));
        $response->assertJsonPath('display_order_number', $order->order_number);
        $this->postJson('/api/v1/guest/orders', $data)->assertCreated()
            ->assertJsonPath('display_order_number', $order->order_number);
        $message = app(\App\Services\OrderWhatsAppMessage::class);
        $text = $message->build($order);
        $this->assertStringContainsString($order->draft->code, $text);
        $this->assertStringContainsString('пока оплачивать ничего не нужно', $text);
        $this->assertStringNotContainsString('https://pay.kaspi.kz', $text);
        $order->update(['status' => 'pending', 'delivery_type' => 'standard', 'delivery_fee' => 3000, 'total' => 4600]);
        $text = $message->build($order->fresh());
        $this->assertStringContainsString('4 600,00', $text);
        $this->assertStringContainsString('https://pay.kaspi.kz/pay/8nohzgrr', $text);
        $this->assertStringContainsString('Квитанцию отправьте', $text);
        $this->post('/o', ['code' => $order->draft->code])->assertRedirect('/o/'.$order->draft->code);
        $this->get('/o/'.$order->draft->code.'/payment')->assertOk()
            ->assertSee('https://pay.kaspi.kz/pay/8nohzgrr')->assertSee('После оплаты отправьте квитанцию');
        $order->update(['payment_reported_at' => now()]);
        $text = $message->build($order->fresh());
        $this->assertStringContainsString('Повторно платить не нужно', $text);
        $this->assertStringNotContainsString('https://pay.kaspi.kz', $text);
        $data['request_id'] = (string) Str::uuid();
        $second = $this->postJson('/api/v1/guest/orders', $data)->assertCreated();
        $this->assertNotSame($order->order_number, $second->json('display_order_number'));
    }

    public function test_short_numbers_are_not_reused_even_in_a_later_year(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-01-09 12:00:00', 'Asia/Almaty'));
        $data = $this->payload();
        Institution::whereKey($data['institution_id'])->update(['order_code' => '07']);
        $this->postJson('/api/v1/guest/orders', $data)->assertCreated();
        $order = Order::firstOrFail();
        $original = $order->order_number;
        $this->assertMatchesRegularExpression('/^0701[0-9]{3}$/', $original);
        $row = $order->getAttributes();
        unset($row['id']);
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $number = '0701'.str_pad($i, 3, '0', STR_PAD_LEFT);
            if ($number === $original) continue;
            $rows[] = array_merge($row, ['order_number' => 'TEST-'.$i, 'display_number' => $number]);
        }
        foreach (array_chunk($rows, 50) as $chunk) Order::insert($chunk);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-09 12:00:00', 'Asia/Almaty'));
        $data['request_id'] = (string) Str::uuid();
        $this->postJson('/api/v1/guest/orders', $data)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 1000);
        $this->assertSame($original, $order->fresh()->order_number);
    }

    public function test_product_images_are_absolute_for_native_clients(): void
    {
        config(['app.url' => 'https://dosmart.aican.cloud']);
        $p = Product::create(['name' => 'Чай', 'price' => 800, 'image_url' => '/storage/products/tea.jpg']);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonFragment([
            'image_url' => 'https://dosmart.aican.cloud/storage/products/tea.jpg',
        ]);
        $this->assertSame('/storage/products/tea.jpg', $p->getRawOriginal('image_url'));
        $p->image_url = 'https://example.com/tea.jpg';
        $this->assertSame('https://example.com/tea.jpg', $p->image_url);
        $p->image_url = null;
        $this->assertNull($p->image_url);
    }

    public function test_phone_consent_and_active_institution_are_required(): void
    {
        $data = $this->payload();
        foreach (['contact_phone', 'consent', 'institution_id'] as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->postJson('/api/v1/guest/orders', $invalid)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        Institution::query()->update(['is_active' => false]);
        $this->postJson('/api/v1/guest/orders', $data)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }
}
