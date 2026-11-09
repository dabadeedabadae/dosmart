<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderWhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $product = Product::create(['name' => 'Мука тестовая', 'price' => 500]);
        $institution = Institution::create(['name' => 'Учреждение', 'city' => 'Алматы']);
        $this->postJson('/api/v1/guest/orders', ['request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'prisoner_name' => 'Тестовый Получатель', 'institution_id' => $institution->id,
            'contact_phone' => '+77011234567', 'consent' => true])->assertCreated();
        return Order::firstOrFail();
    }

    public function test_short_formatted_messages_and_contextual_templates_render_for_staff(): void
    {
        $order = $this->order();
        $service = app(OrderWhatsAppMessage::class);
        $text = $service->build($order);
        $this->assertLessThan(650, mb_strlen($text));
        $this->assertStringStartsWith('*Dosmart · заявка № ', $text);
        $this->assertStringContainsString(route('pilot.payment', $order->draft->code), $text);
        $this->assertStringNotContainsString('Мука тестовая', $text);
        $this->assertStringNotContainsString('Код открытия:', $text);
        $this->assertStringNotContainsString('Kaspi:', $text);
        $this->assertStringContainsString('*Итого с доставкой: 4 000,00 ₸*', $text);
        $this->assertStringContainsString('включена в сумму', $text);
        $this->assertEquals(1000, $order->total);
        $templates = $service->templates($order);
        foreach ($templates as $template) {
            $this->assertStringContainsString('Получатель: *Тестовый Получатель*', $template['text']);
        }
        $this->assertArrayHasKey('reminder', $templates);
        $this->assertArrayHasKey('changes', $templates);
        $this->actingAs(User::factory()->create())->get('/admin/orders/'.$order->id)
            ->assertOk()->assertSee('Дополнительные сообщения')->assertSee('Напомнить о согласовании')
            ->assertSee('Предварительно с доставкой')->assertSee('4 000')
            ->assertSee('admin-mobile.css')->assertSee('admin-mobile.js');
    }

    public function test_payment_messages_never_request_payment_when_blocked_or_already_reported(): void
    {
        $order = $this->order();
        $service = app(OrderWhatsAppMessage::class);
        config(['pilot.kaspi_url' => 'https://pay.kaspi.kz/pay/example']);
        $order->update(['status' => 'pending', 'total' => 4000, 'delivery_fee' => 3000]);
        $templates = $service->templates($order->fresh());
        $this->assertStringContainsString('*К оплате: 4 000,00 ₸*', $templates['main']['text']);
        $this->assertStringContainsString('https://pay.kaspi.kz', $templates['main']['text']);
        $this->assertStringContainsString('*номер заявки и ФИО получателя*', $templates['main']['text']);
        $order->changeRequests()->create(['items' => [], 'comment' => 'Изменить']);
        $templates = $service->templates($order->fresh());
        $this->assertStringNotContainsString('Kaspi:', $templates['main']['text']);
        $this->assertArrayNotHasKey('reminder', $templates);
        $order->changeRequests()->delete();
        $order->update(['payment_reported_at' => now()]);
        $templates = $service->templates($order->fresh());
        $this->assertStringContainsString('Повторно платить не нужно', $templates['main']['text']);
        $this->assertArrayNotHasKey('reminder', $templates);
        $this->assertArrayNotHasKey('changes', $templates);
        foreach (['delivering', 'delivered', 'cancelled', 'refunded'] as $status) {
            $order->update(['status' => $status]);
            $templates = $service->templates($order->fresh());
            $this->assertStringContainsString('Получатель: *Тестовый Получатель*', $templates['main']['text']);
            $this->assertArrayNotHasKey('reminder', $templates);
            $this->assertArrayNotHasKey('changes', $templates);
            $this->assertStringNotContainsString('Kaspi:', $templates['main']['text']);
        }
    }
}
