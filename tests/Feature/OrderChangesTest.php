<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderChanges;
use App\Services\PilotCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderChangesTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $p = Product::create(['name' => 'Чай проверочный', 'price' => 800]);
        $i = Institution::create(['name' => 'Учреждение', 'city' => 'Алматы', 'is_active' => true]);
        $draft = app(PilotCheckout::class)->create(['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $p->id, 'quantity' => 2]]], 'website');

        return app(PilotCheckout::class)->checkout($draft, ['prisoner_name' => 'Скрытые ФИО', 'squad_number' => '7', 'institution_id' => $i->id, 'contact_phone' => '+77011234567', 'delivery_type' => 'standard', 'expected_total' => 4600], null);
    }

    private function change(Order $o, int $qty = 1): array
    {
        return ['revision' => app(OrderChanges::class)->revision($o->fresh()), 'items' => [['id' => $o->items->first()->id, 'quantity' => $qty]], 'comment' => 'Уберите одну пачку'];
    }

    public function test_relative_can_view_request_changes_and_pay_after_staff_confirmation(): void
    {
        config(['pilot.kaspi_url' => 'https://pay.kaspi.kz/pay/8nohzgrr']);
        $o = $this->order();
        $url = '/o/'.$o->draft->code;
        $this->assertSame('7', $o->squad_number);
        $this->get($url)->assertRedirect($url.'/payment');
        $this->get($url.'/payment')->assertOk()->assertSee('Чай проверочный')->assertSee('Изменить заявку')->assertSee('Оплатить через Kaspi')->assertDontSee('Скрытые ФИО')->assertDontSee('+77011234567');
        $data = $this->change($o);
        $before = $data['revision'];
        $this->post($url.'/changes', $data)->assertRedirect($url.'/payment');
        $this->assertSame('4600.00', $o->fresh()->total);
        $this->get($url.'/payment')->assertSee('Запрос изменений отправлен')->assertDontSee('Оплатить через Kaspi');
        $this->post($url.'/changes', $data)->assertSessionHasErrors('changes');
        $this->post($url.'/payment-reported')->assertSessionHasErrors('changes');
        $this->assertNull($o->fresh()->payment_reported_at);
        $change = $o->changeRequests()->firstOrFail();
        $review = '/admin/orders/'.$o->id.'/changes/'.$change->id;
        $this->post($review, ['decision' => 'approved'])->assertRedirect('/admin/login');
        $admin = User::factory()->create();
        $this->actingAs($admin)->get('/admin/orders/'.$o->id)->assertOk()->assertSee('Нужно рассмотреть');
        $this->post($review, ['decision' => 'approved', 'response' => 'Согласовано'])->assertRedirect();
        $this->assertSame('3800.00', $o->fresh()->total);
        $this->assertSame(1, $o->items()->first()->quantity);
        $this->assertSame($admin->id, $change->fresh()->reviewed_by);
        $this->post($review, ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('3800.00', $o->fresh()->total);
        $this->get($url.'/payment')->assertSee('Оплатить через Kaspi')->assertSee('Согласовано');
        $this->getJson($url.'/status?revision='.$before)->assertOk()->assertJsonStructure(['html', 'revision'])->assertDontSee('Скрытые ФИО');
        $this->post($url.'/payment-reported', ['revision' => $before])->assertSessionHasErrors('changes');
        $this->post($url.'/payment-reported', ['revision' => app(OrderChanges::class)->revision($o->fresh())])->assertRedirect();
        $this->get($url.'/payment')->assertSee('Заказ в обработке')->assertDontSee('Оплатить через Kaspi')->assertDontSee('data-change-form>', false);
        $this->post($url.'/changes', $this->change($o))->assertSessionHasErrors('changes');
    }

    public function test_new_products_are_added_only_after_review_and_use_checked_prices(): void
    {
        $o = $this->order();
        $product = Product::create(['name' => 'Новый хлеб', 'price' => 500]);
        $data = $this->change($o, 0);
        $data['additions'] = [['product_id' => $product->id, 'quantity' => 3, 'price' => 1]];
        $url = '/o/'.$o->draft->code;
        $this->get($url.'/payment')->assertSee('Добавить товары')->assertSee('Новый хлеб');
        $this->post($url.'/changes', $data)->assertSessionHasErrors('changes');
        $data['additions'][0]['price'] = 500;
        $this->post($url.'/changes', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('4600.00', $o->fresh()->total);
        $this->assertFalse($o->items()->where('product_id', $product->id)->exists());
        $change = $o->changeRequests()->firstOrFail();
        $review = '/admin/orders/'.$o->id.'/changes/'.$change->id;
        $admin = User::factory()->create();
        $product->update(['in_stock' => false]);
        $this->actingAs($admin)->post($review, ['decision' => 'approved'])->assertSessionHasErrors('changes');
        $this->assertSame(2, $o->items()->first()->quantity);
        $product->update(['in_stock' => true, 'price' => 600]);
        $this->post($review, ['decision' => 'approved'])->assertSessionHasErrors('changes');
        $product->update(['price' => 500]);
        $this->post($review, ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('4500.00', $o->fresh()->total);
        $this->assertSame(1, $o->items()->count());
        $this->assertSame(3, $o->items()->first()->quantity);
        $this->post($review, ['decision' => 'approved'])->assertRedirect();
        $this->assertSame(1, $o->items()->count());
    }

    public function test_foreign_items_stale_forms_empty_cart_and_wrong_order_review_are_rejected(): void
    {
        $o = $this->order();
        $other = $this->order();
        $url = '/o/'.$o->draft->code.'/changes';
        $data = $this->change($o);
        $data['items'][0]['id'] = $other->items->first()->id;
        $this->post($url, $data)->assertSessionHasErrors('changes');
        $data = $this->change($o);
        $data['revision'] = str_repeat('0', 64);
        $this->post($url, $data)->assertSessionHasErrors('changes');
        $this->post($url, $this->change($o, 0))->assertSessionHasErrors('changes');
        $this->post($url, $this->change($o))->assertRedirect();
        $change = $o->changeRequests()->firstOrFail();
        $this->actingAs(User::factory()->create())->post('/admin/orders/'.$other->id.'/changes/'.$change->id, ['decision' => 'approved'])->assertNotFound();
        $this->patch('/admin/orders/'.$o->id.'/status', ['status' => 'paid', 'payment_verified' => 1, 'payment_reference' => 'test'])->assertSessionHasErrors('changes');
        $this->post('/admin/orders/'.$o->id.'/changes/'.$change->id, ['decision' => 'rejected', 'response' => 'Нет в наличии'])->assertRedirect();
        $this->assertSame('4600.00', $o->fresh()->total);
        $this->assertSame('rejected', $change->fresh()->status);
        $this->get('/o/ZZZZZZZZ/payment')->assertNotFound();
        $this->post('/o/ZZZZZZZZ/changes', $data)->assertNotFound();
    }
}
