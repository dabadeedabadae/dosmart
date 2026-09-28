<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_normalizes_phone_and_does_not_grant_admin_access(): void
    {
        $this->post('/shop/register', ['phone' => '8 (700) 123-45-67', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect('/shop/cart');
        $customer = Customer::first();
        $this->assertSame('+77001234567', $customer->phone);
        $this->assertTrue(Hash::check('password123', $customer->password));
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->get('/admin/products')->assertRedirect('/admin/login');
        $this->get('/shop/checkout')->assertRedirect('/shop/cart');
        $this->post('/shop/logout')->assertRedirect('/shop');
        $this->assertGuest('customer');
        $this->post('/shop/login', ['phone' => '+7 700 123 45 67', 'password' => 'wrong'])->assertSessionHasErrors('phone');
        $this->post('/shop/login', ['phone' => '+7 700 123 45 67', 'password' => 'password123'])->assertRedirect('/shop/cart');
    }

    public function test_duplicate_phone_and_mismatched_password_are_rejected(): void
    {
        Customer::create(['phone' => '+77001234567', 'password' => 'password123']);
        $this->post('/shop/register', ['phone' => '87001234567', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasErrors('phone');
        $this->post('/shop/register', ['phone' => '+77009999999', 'password' => 'password123', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
    }

    public function test_catalog_has_all_published_products_search_and_pagination(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            Product::create(['name' => 'Product '.$i, 'price' => $i * 100, 'is_active' => true]);
        }
        Product::create(['name' => 'Hidden', 'price' => 10, 'is_active' => false]);
        $this->get('/shop')->assertOk()->assertSee('26 товаров')->assertDontSee('Hidden');
        $this->get('/shop?page=2')->assertOk()->assertSee('Product 25');
        $this->get('/shop?q=Product+26')->assertOk()->assertSee('Product 26')->assertDontSee('Product 25');
        $this->get('/shop/login')->assertOk();
        $this->get('/shop/register')->assertOk();
    }

    public function test_old_order_endpoints_cannot_bypass_pilot_checkout(): void
    {
        $this->postJson('/shop/orders', [])->assertUnauthorized();
        $this->postJson('/api/v1/orders', [])->assertUnauthorized();
        $customer = Customer::create(['phone' => '+77001234567', 'password' => 'password123']);
        $this->actingAs($customer, 'customer')->postJson('/shop/orders', [])->assertGone();
        $this->postJson('/api/v1/orders', [])->assertGone();
    }
}
