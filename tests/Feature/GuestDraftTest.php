<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\OrderDraft;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_needs_no_soilephone_session_and_prices_are_server_side(): void
    {
        Http::preventStrayRequests();
        config(['pilot.terminal_identity_url' => '', 'pilot.terminal_token' => '', 'app.url' => 'https://dosmart.aican.cloud']);
        $product = Product::create(['name' => 'Чай', 'price' => 800]);
        $institution = Institution::create(['name' => 'Учреждение', 'city' => 'Алматы', 'is_active' => true]);
        $data = ['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1]],
            'prisoner_name' => 'Иванов Иван', 'institution_id' => $institution->id, 'terminal_subject' => 'forged', 'status' => 'paid'];
        $result = $this->postJson('/api/v1/guest/drafts', $data)->assertCreated()->assertJsonPath('subtotal', 1600);
        $draft = OrderDraft::first();
        $this->assertNull($draft->terminal_subject);
        $this->assertSame('website', $draft->source);
        $this->assertSame('Иванов Иван', $draft->prisoner_name);
        $this->assertSame($institution->id, $draft->institution_id);
        $this->assertStringContainsString('/o/'.$draft->code, $result->json('chat_text'));
        $this->postJson('/api/v1/guest/drafts', $data)->assertJsonPath('code', $draft->code);
        $this->assertDatabaseCount('order_drafts', 1);
        $this->assertDatabaseCount('orders', 0);
        $this->get('/o/'.$draft->code)->assertOk()->assertSee('Чай');
        $data['items'][0]['quantity'] = 3;
        $this->postJson('/api/v1/guest/drafts', $data)->assertConflict();
        $institution->update(['is_active' => false]);
        $data['request_id'] = (string) Str::uuid();
        $this->postJson('/api/v1/guest/drafts', $data)->assertUnprocessable();
        $this->postJson('/api/v1/drafts', $data)->assertUnauthorized();
    }
}
