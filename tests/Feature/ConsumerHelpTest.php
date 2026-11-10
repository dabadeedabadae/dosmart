<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsumerHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_is_public_in_local_preview_and_uses_explicit_missing_data_labels(): void
    {
        $this->get('/help')->assertNotFound();
        config(['consumer.preview' => true]);

        $this->get('/help')->assertOk()->assertSee('Уголок потребителя')
            ->assertSee('Возврат и претензии')->assertSee(route('shop.index').'#catalog');
        $this->get('/help/rights?lang=kk')->assertOk()->assertSee('Сатып алушының құқықтары')
            ->assertSee('https://eotinish.kz/kk')->assertSee('https://adilet.zan.kz/kaz/docs/Z100000274_');
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
        $this->get('/shop')->assertOk()->assertSee('Уголок потребителя')->assertSee('1 пачка')->assertSee('Чай чёрный');
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

    public function test_help_downloads_the_word_guide_and_links_to_the_online_catalog(): void
    {
        $disk = Storage::fake('local');
        config(['consumer.preview' => true]);
        $this->get('/help')->assertOk()->assertDontSee(route('shop.help.download'));
        $this->get('/help/documents/consumer-guide')->assertNotFound();
        $disk->put('consumer/consumer-guide-ru.docx', 'guide content');
        $this->get('/help?lang=kk')->assertOk()
            ->assertSee(route('shop.help.download'))->assertSee(route('shop.index').'#catalog')
            ->assertDontSee('/help/documents/catalog');
        $this->get('/help/documents/catalog')->assertNotFound();
        $this->get('/help/documents/other')->assertNotFound();

        $response = $this->get('/help/documents/consumer-guide')
            ->assertOk()
            ->assertDownload('consumer-guide-ru.docx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertSame('guide content', $response->streamedContent());

        config(['consumer.preview' => false]);
        $this->get('/help/documents/consumer-guide')->assertNotFound();
    }

    public function test_cutover_updates_only_unpaid_orders_and_refreshes_open_pages(): void
    {
        config(['pilot.kaspi_url' => 'https://pay.kaspi.kz/pay/old']);
        $order = \App\Models\Order::create([
            'order_number' => 'DOS-CUTOVER', 'display_number' => '0710925',
            'prisoner_name' => 'Тестовый получатель', 'institution_name' => 'Демо', 'contact_phone' => '',
            'status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => 950, 'delivery_fee' => 3000, 'total' => 3950,
        ]);
        $draft = \App\Models\OrderDraft::create([
            'request_id' => (string) Str::uuid(), 'code' => 'CUTOVER2', 'source' => 'website',
            'items' => [], 'expires_at' => now()->addDay(), 'order_id' => $order->id,
        ]);
        $path = '/o/'.$draft->code;
        $revision = app(\App\Services\OrderChanges::class)->revision($order->fresh());
        $this->get($path.'/payment')->assertOk()->assertSee('https://pay.kaspi.kz/pay/old');
        config(['pilot.kaspi_replacement_url' => 'https://pay.kaspi.kz/pay/new']);
        $this->getJson($path.'/status?revision='.$revision)->assertOk()->assertJsonStructure(['html', 'revision']);
        $this->get($path.'/payment')->assertOk()->assertSee('https://pay.kaspi.kz/pay/new')->assertDontSee('https://pay.kaspi.kz/pay/old');
        $this->assertStringContainsString('https://pay.kaspi.kz/pay/new',
            app(\App\Services\OrderWhatsAppMessage::class)->templates($order)['main']['text']);

        config(['pilot.kaspi_protected_orders' => [$order->order_number]]);
        $this->getJson($path.'/status?revision='.$revision)->assertOk()->assertJsonMissingPath('html');
        $this->get($path.'/payment')->assertOk()->assertSee('https://pay.kaspi.kz/pay/old')->assertDontSee('https://pay.kaspi.kz/pay/new');
        $this->assertStringContainsString('https://pay.kaspi.kz/pay/old',
            app(\App\Services\OrderWhatsAppMessage::class)->templates($order)['main']['text']);

        config(['pilot.kaspi_protected_orders' => []]);
        $this->post($path.'/payment-reported', ['revision' => $revision])->assertRedirect();
        $this->get($path.'/payment')->assertOk()->assertDontSee('https://pay.kaspi.kz/pay/new')->assertDontSee('https://pay.kaspi.kz/pay/old');
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $order->update(['status' => 'processing', 'payment_status' => 'paid']);
        $this->get($path.'/payment')->assertOk()->assertDontSee('https://pay.kaspi.kz/pay/new');
    }

    public function test_published_help_omits_unconfirmed_details(): void
    {
        config(['consumer.preview' => false, 'consumer.published' => true]);
        $this->get('/help/delivery')->assertOk()->assertDontSee('Ожидает подтверждения владельца ИП')
            ->assertDontSee('Как получить фискальный чек');
    }

}
