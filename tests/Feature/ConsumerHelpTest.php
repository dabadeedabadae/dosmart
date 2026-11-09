<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsumerHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_is_public_in_local_preview_and_uses_explicit_missing_data_labels(): void
    {
        $this->get('/help')->assertNotFound();
        config(['consumer.preview' => true]);

        $this->get('/help')->assertOk()->assertSee('Возврат и претензии');
        $this->get('/help/seller')->assertOk()->assertSee('Ожидает подтверждения владельца ИП');
        $this->get('/help/returns?lang=kk')->assertOk()->assertSee('Қайтару және наразылықтар')->assertSee('күнтізбелік 10 күн');
        $this->get('/help/delivery')->assertOk()->assertSee('3 000 ₸');
        $this->get('/help/unknown')->assertNotFound();
        $this->get('/privacy')->assertOk()->assertSee('Контакты и сведения об обработке данных');
    }

    public function test_help_links_are_visible_before_order_submission_and_payment(): void
    {
        config(['consumer.preview' => true]);
        $institution = Institution::create(['name' => 'Учреждение 1', 'city' => 'Алматы', 'is_active' => true]);
        $product = Product::create(['name' => 'Чай', 'price' => 1000, 'unit' => '1 пачка', 'description' => 'Чай чёрный']);
        $this->get('/shop')->assertOk()->assertSee('Помощь покупателю')->assertSee('1 пачка')->assertSee('Чай чёрный');
        $this->get('/shop/cart')->assertOk()->assertSee('Возврат и претензии');
        $code = $this->postJson('/api/v1/guest/drafts', [
            'request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('code');
        $this->get('/o/'.$code)->assertOk()->assertSee('Условия покупки');
        $this->post('/o/'.$code, [
            'prisoner_name' => 'Тестовый Получатель', 'institution_id' => $institution->id,
            'contact_phone' => '+7 705 621 8748', 'delivery_type' => 'standard', 'consent' => 1, 'expected_total' => 4000,
        ])->assertRedirect('/o/'.$code.'/payment');
        $this->get('/o/'.$code.'/payment')->assertOk()->assertSee('Возврат и претензии');
    }
}
