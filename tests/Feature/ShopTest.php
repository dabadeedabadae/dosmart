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

    public function test_shop_has_no_customer_login_or_registration(): void
    {
        $this->get('/shop/login')->assertRedirect('/shop');
        $this->get('/shop/register')->assertRedirect('/shop');
        $this->post('/shop/register', ['phone' => '87001234567', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect('/shop');
        $this->assertSame(0, Customer::count());
        $this->assertGuest('customer');
        $this->get('/shop')->assertOk()->assertDontSee('Регистрация')->assertDontSee('Войти')->assertDontSee('Вход для сотрудников');
        // Вход для сотрудников остаётся по прямой ссылке.
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/products')->assertRedirect('/admin/login');
    }

    public function test_catalog_has_all_published_products_search_and_pagination(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            Product::create(['name' => 'Product '.$i, 'price' => $i * 100, 'is_active' => true]);
        }
        Product::create(['name' => 'Hidden', 'price' => 10, 'is_active' => false]);
        $this->get('/shop')->assertOk()->assertSee('Товаров: 26')->assertDontSee('Hidden');
        $this->get('/shop?page=2')->assertOk()->assertSee('Product 25');
        $this->get('/shop?q=Product+26')->assertOk()->assertSee('Product 26')->assertDontSee('Product 25');
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
