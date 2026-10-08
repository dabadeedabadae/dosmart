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
            'prisoner_name' => 'Иванов Иван Иванович', 'institution_id' => $i->id,
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
        $this->assertSame('unconfirmed', $o->delivery_type);
        $this->assertSame('Учреждение №1', $o->institution_name);
        $this->assertSame('+77011234567', $o->contact_phone);
        $this->assertSame('website', $o->draft->source);
        $this->assertArrayNotHasKey('contact_phone', $r->json());
        Product::query()->update(['in_stock' => false]);
        $this->postJson('/api/v1/guest/orders', $data)->assertCreated()->assertJsonPath('order_number', $o->order_number);
        $data['contact_phone'] = '+77017654321';
        $this->postJson('/api/v1/guest/orders', $data)->assertConflict();
        $this->assertDatabaseCount('orders', 1);
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
