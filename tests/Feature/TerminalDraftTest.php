<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\OrderDraft;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TerminalDraftTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $p = Product::create(['name' => 'Чай', 'price' => 800]);

        return ['request_id' => (string) Str::uuid(), 'items' => [['product_id' => $p->id, 'quantity' => 2]], 'prisoner_name' => 'Подменённое имя', 'institution_id' => 999];
    }

    public function test_verified_session_supplies_identity_and_scopes_idempotency(): void
    {
        $institution = Institution::create(['name' => 'Учреждение', 'city' => 'Алматы', 'is_active' => true]);
        config(['pilot.terminal_identity_url' => 'https://identity.example/api/user', 'pilot.terminal_institution_map' => ['42' => $institution->id]]);
        $userId = 7;
        Http::fake(['identity.example/*' => function () use (&$userId) {
            return Http::response(['id' => $userId, 'id_number' => '123-456', 'last_name' => 'Тестов', 'first_name' => 'Тест', 'establishment' => ['id' => 42]]);
        }]);
        $payload = $this->payload();
        $result = $this->withToken('user-session')->postJson('/api/v1/terminal/drafts', $payload)->assertCreated();
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer user-session'));
        $draft = OrderDraft::first();
        $this->assertSame('Тестов Тест', $draft->prisoner_name);
        $this->assertSame($institution->id, $draft->institution_id);
        $this->assertSame('terminal', $draft->source);
        $this->withToken('user-session')->postJson('/api/v1/terminal/drafts', $payload)->assertJsonPath('code', $result->json('code'));
        $this->withToken('user-session')->postJson('/api/v1/terminal/drafts/'.$draft->code.'/sent')->assertNoContent();
        $this->withToken('user-session')->postJson('/api/v1/terminal/drafts/'.$draft->code.'/sent')->assertNoContent();
        $this->assertDatabaseCount('pilot_events', 2);
        $userId = 8;
        $this->withToken('other-session')->postJson('/api/v1/terminal/drafts', $payload)->assertConflict();
        $this->withToken('other-session')->postJson('/api/v1/terminal/drafts/'.$draft->code.'/sent')->assertNotFound();
    }

    public function test_identity_verification_fails_closed_and_never_uses_client_name(): void
    {
        $payload = $this->payload();
        config(['pilot.terminal_identity_url' => '']);
        $status = 401;
        Http::fake(['identity.example/*' => function () use (&$status) {
            return Http::response(['id' => 7, 'id_number' => 'valid', 'first_name' => 'Проверенный'], $status);
        }]);
        $this->withToken('session')->postJson('/api/v1/terminal/drafts', $payload)->assertStatus(503);
        Http::assertNothingSent();
        config(['pilot.terminal_identity_url' => 'http://identity.example/api/user']);
        $this->withToken('session')->postJson('/api/v1/terminal/drafts', $payload)->assertStatus(503);
        Http::assertNothingSent();
        config(['pilot.terminal_identity_url' => 'https://identity.example/api/user']);

        $this->withToken('expired')->postJson('/api/v1/terminal/drafts', $payload)->assertUnauthorized();
        $status = 200;
        $this->withToken('valid')->postJson('/api/v1/terminal/drafts', $payload)->assertCreated();
        $this->assertNull(OrderDraft::first()->institution_id);
        $this->assertSame('Проверенный', OrderDraft::first()->prisoner_name);
    }
}
