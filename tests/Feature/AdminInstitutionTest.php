<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Order;
use App\Models\OrderDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_require_staff_login(): void
    {
        $institution = Institution::create(['name' => '№1', 'city' => 'Алматы']);
        foreach (['/admin/institutions', '/admin/institutions/create', '/admin/institutions/'.$institution->id.'/edit'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
        $this->post('/admin/institutions', [])->assertRedirect('/admin/login');
        $this->put('/admin/institutions/'.$institution->id, [])->assertRedirect('/admin/login');
        $this->patch('/admin/institutions/'.$institution->id.'/activity', ['is_active' => 0])->assertRedirect('/admin/login');
        $this->delete('/admin/institutions/'.$institution->id)->assertRedirect('/admin/login');
        $this->assertDatabaseCount('institutions', 1);
    }

    public function test_staff_crud_activity_and_public_list(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin/institutions')->assertOk()->assertSee('Учреждений нет');
        $this->get('/admin/institutions/create')->assertOk()->assertSee('Город');
        $this->post('/admin/institutions', ['name' => '№12', 'city' => 'Алматы', 'address' => 'Улица 1', 'is_active' => 0])->assertRedirect('/admin/institutions');
        $institution = Institution::firstOrFail();
        $this->assertFalse($institution->is_active);
        $this->getJson('/api/v1/institutions')->assertJsonCount(0);
        $url = '/admin/institutions/'.$institution->id;
        $this->get($url.'/edit')->assertOk()->assertSee('Улица 1');
        $this->get('/admin/institutions?edit='.$institution->id)->assertOk()->assertSee('Редактировать учреждение');
        $this->put($url, ['name' => '№13', 'city' => 'Астана', 'address' => null, 'is_active' => 1])->assertRedirect('/admin/institutions');
        $this->assertDatabaseHas('institutions', ['id' => $institution->id, 'name' => '№13', 'city' => 'Астана', 'address' => null]);
        $this->getJson('/api/v1/institutions')->assertJsonCount(1)->assertJsonPath('0.name', '№13');
        $this->patch($url.'/activity', ['is_active' => 0])->assertRedirect('/admin/institutions');
        $this->getJson('/api/v1/institutions')->assertJsonCount(0);
        $this->patch($url.'/activity', ['is_active' => 1])->assertRedirect('/admin/institutions');
        $this->assertTrue($institution->fresh()->is_active);
        $this->delete($url)->assertRedirect('/admin/institutions')->assertSessionHas('success');
        $this->assertDatabaseCount('institutions', 0);
    }

    public function test_validation_and_linked_record_deletion_protection(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/admin/institutions', ['name' => '', 'city' => '', 'is_active' => 'bad'])->assertSessionHasErrors(['name', 'city', 'is_active']);
        $institution = Institution::create(['name' => '№12', 'city' => 'Алматы']);
        $url = '/admin/institutions/'.$institution->id;
        $this->put($url, ['name' => str_repeat('a', 256), 'city' => 'Город', 'is_active' => 1])->assertSessionHasErrors('name');
        $this->patch($url.'/activity', ['is_active' => 'bad'])->assertSessionHasErrors('is_active');
        $draft = OrderDraft::create(['code' => '7K3M9A2B', 'request_id' => (string) Str::uuid(), 'source' => 'website', 'items' => [], 'institution_id' => $institution->id, 'expires_at' => now()->addDay()]);
        $this->delete($url)->assertSessionHas('error');
        $this->assertDatabaseHas('institutions', ['id' => $institution->id]);
        $draft->delete();
        Order::create(['order_number' => 'DOS-TEST', 'institution_id' => $institution->id, 'institution_name' => $institution->name, 'prisoner_name' => 'Тестов Тест', 'contact_phone' => '+77011234567', 'subtotal' => 100, 'delivery_fee' => 3000, 'total' => 3100]);
        $this->delete($url)->assertSessionHas('error');
        $this->assertDatabaseHas('institutions', ['id' => $institution->id]);
        $this->patch($url.'/activity', ['is_active' => 0])->assertRedirect('/admin/institutions');
        $this->assertDatabaseCount('orders', 1);
    }
}
