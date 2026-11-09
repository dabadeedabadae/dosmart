<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_is_private_and_contains_recipient_and_order_snapshot(): void
    {
        $order = Order::create([
            'order_number' => 'DOS-TEST', 'display_number' => '0710483',
            'prisoner_name' => 'Иванов Иван', 'squad_number' => '12',
            'institution_name' => 'Учреждение 07', 'contact_phone' => '+77011234567',
            'subtotal' => 900, 'delivery_fee' => 3000, 'total' => 3900,
            'status' => 'pending', 'payment_status' => 'pending',
        ]);
        $order->items()->create(['product_name' => 'Чай <script>alert(1)</script>', 'price' => 900, 'quantity' => 1, 'total' => 900]);
        $url = route('admin.orders.receipt', $order);
        $this->get($url)->assertRedirect(route('admin.login'));
        $response = $this->actingAs(User::factory()->create())->get($url);
        $response->assertOk()->assertSee('Иванов Иван')->assertSee('0710483')
            ->assertSee('Отряд:')->assertSee('12')->assertSee('Учреждение 07')
            ->assertSee('3 900,00 тг')->assertSee('Чай &lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('+77011234567')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $draft = \App\Models\OrderDraft::create(['code'=>'TESTABCD', 'request_id'=>(string)\Illuminate\Support\Str::uuid(), 'source'=>'website', 'items'=>[], 'order_id'=>$order->id, 'expires_at'=>now()->addDay()]);
        auth()->logout();
        $this->get('/o/TESTABCD/receipt')->assertOk()->assertSee('3 900,00 тг')->assertSee('Печать / сохранить PDF')
            ->assertDontSee('Иванов Иван')->assertDontSee('Учреждение 07')->assertDontSee('+77011234567');
        $this->get('/o/0710483/receipt')->assertNotFound();
        $this->actingAs(User::factory()->create());
        $context = ['q'=>'0710483', 'status'=>'pending', 'page'=>2];
        $this->get(route('admin.orders.show', ['order'=>$order, 'list'=>$context]))->assertOk()
            ->assertSee(e(route('admin.orders.index', $context)), false)->assertSee('Подтвердить оплату')->assertDontSee('name="refund_amount"', false);
        $this->patch(route('admin.orders.status', ['order'=>$order, 'list'=>$context]), ['status'=>'paid'])->assertSessionHasErrors('payment_verified');
        $this->patch(route('admin.orders.status', ['order'=>$order, 'list'=>$context]), ['status'=>'paid','payment_verified'=>1,'payment_reference'=>'TEST-PAYMENT'])
            ->assertRedirect(route('admin.orders.show', ['order'=>$order, 'list'=>$context]));
        $this->get(route('admin.orders.show', $order))->assertSee('Начать сборку')->assertSee('Зафиксировать возврат')->assertDontSee('name="payment_reference"', false);
        $order->update(['status' => 'new', 'squad_number' => null]);
        $this->get($url)->assertOk()->assertSee('Не указан')->assertSee('пока не согласованы')->assertDontSee('3 900,00 тг');
    }
}
