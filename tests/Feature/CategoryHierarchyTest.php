<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_exposes_nested_filters_and_parent_product_counts(): void
    {
        $root = Category::create(['name' => 'Бакалея']);
        $child = Category::create(['name' => 'Мучное', 'parent_id' => $root->id]);
        $leaf = Category::create(['name' => 'Макароны', 'parent_id' => $child->id]);
        $other = Category::create(['name' => 'Напитки']);
        $flour = Product::create(['name' => 'Мука', 'category_id' => $child->id, 'price' => 500]);
        $pasta = Product::create(['name' => 'Спагетти', 'category_id' => $leaf->id, 'price' => 800]);
        Product::create(['name' => 'Нет в наличии', 'category_id' => $leaf->id, 'price' => 100, 'in_stock' => false]);
        Product::create(['name' => 'Вода', 'category_id' => $other->id, 'price' => 200]);
        $response = $this->getJson('/api/v1/categories')->assertOk();
        $tree = collect($response->json())->firstWhere('id', $root->id);
        $this->assertSame(2, $tree['product_count']);
        $this->assertSame([$root->id, $child->id, $leaf->id], $tree['category_ids']);
        $this->assertSame('Мучное', $tree['children'][0]['name']);
        $this->assertSame('Макароны', $tree['children'][0]['children'][0]['name']);
        $this->assertSame([$flour->id, $pasta->id], collect($this->getJson('/api/v1/products?category_id='.$root->id)->assertOk()->json())->pluck('id')->sort()->values()->all());
        $this->getJson('/api/v1/products?category_id='.$leaf->id)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $pasta->id);
    }

    public function test_product_edit_preserves_list_context_and_restricts_return_parameters(): void
    {
        $category = Category::create(['name' => 'Бакалея']);
        $product = Product::create(['name' => 'Чай', 'category_id' => $category->id, 'price' => 100]);
        $this->actingAs(User::factory()->create());
        $context = ['category_id' => $category->id, 'page' => 2, 'q' => 'Чай'];
        $url = route('admin.products.edit', ['product' => $product, 'list' => $context]);
        $this->get($url)->assertOk()->assertSee('name="list[page]"', false)->assertSee('value="2"', false);
        $this->put(route('admin.products.update', $product), ['name' => 'Чай', 'category_id' => $category->id, 'price' => 200, 'list' => $context + ['redirect' => 'https://example.com']])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.products.index', $context));
        $this->get('/admin/products?q=Кофе')->assertOk()->assertViewHas('products', fn ($items) => $items->total() === 0);
        $this->get('/admin/products?q=Чай')->assertOk()->assertViewHas('products', fn ($items) => $items->total() === 1);
        $this->put(route('admin.products.update', $product), ['name' => 'Чай', 'category_id' => $category->id, 'price' => 200, 'list' => 'https://example.com'])
            ->assertRedirect(route('admin.products.index'));
    }

    public function test_admin_filter_includes_all_descendants_and_preserves_filter_on_next_page(): void
    {
        $root = Category::create(['name' => 'Бакалея']);
        $child = Category::create(['name' => 'Выпечка', 'parent_id' => $root->id]);
        $leaf = Category::create(['name' => 'Хлеб', 'parent_id' => $child->id, 'is_active' => false]);
        $other = Category::create(['name' => 'Другая']);
        $direct = Product::create(['name' => 'Прямой', 'category_id' => $root->id, 'price' => 100]);
        Product::create(['name' => 'Чужой', 'category_id' => $other->id, 'price' => 100]);
        for ($i = 0; $i < 31; $i++) {
            Product::create(['name' => 'Вложенный '.$i, 'category_id' => $leaf->id, 'price' => 100, 'is_active' => false]);
        }
        $this->actingAs(User::factory()->create());
        $response = $this->get('/admin/products?category_id='.$root->id)->assertOk();
        $products = $response->viewData('products');
        $this->assertSame(32, $products->total());
        $this->assertTrue($products->contains('id', $direct->id));
        $this->assertStringContainsString('category_id='.$root->id, $products->nextPageUrl());
        $second = $this->get($products->nextPageUrl())->assertOk()->viewData('products');
        $this->assertCount(2, $second->items());
        $this->assertTrue($second->every(fn ($product) => $product->category_id === $leaf->id));
        $this->assertSame(31, $this->get('/admin/products?category_id='.$child->id)->assertOk()->viewData('products')->total());
        $this->assertSame(31, $this->get('/admin/products?category_id='.$leaf->id)->assertOk()->viewData('products')->total());
        $this->assertSame(33, $this->get('/admin/products')->assertOk()->viewData('products')->total());
    }

    public function test_categories_support_nested_products_and_reject_cycles_and_parent_deletion(): void
    {
        $root = Category::create(['name' => 'Бакалея']);
        $this->actingAs(User::factory()->create());
        $this->post('/admin/categories', ['name' => 'Выпечка', 'parent_id' => $root->id])->assertSessionHasNoErrors()->assertRedirect();
        $child = Category::where('name', 'Выпечка')->firstOrFail();
        $leaf = Category::create(['name' => 'Хлеб', 'parent_id' => $child->id]);
        $this->put('/admin/categories/'.$root->id, ['name' => 'Бакалея', 'parent_id' => $leaf->id])->assertSessionHasErrors('parent_id');
        $this->put('/admin/categories/'.$child->id, ['name' => 'Выпечка', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
        $this->assertNull($root->fresh()->parent_id);
        $this->delete('/admin/categories/'.$root->id)->assertSessionHas('error');
        $this->assertModelExists($root);
        $this->get('/admin/categories?edit='.$child->id)->assertOk()->assertSee('Родительская категория');
        $this->get('/admin/products/create')->assertOk()->assertSee('Бакалея / Выпечка / Хлеб');
        $this->post('/admin/products', ['name' => 'Прямой товар', 'category_id' => $root->id, 'price' => 500])->assertSessionHasNoErrors();
        $this->post('/admin/products', ['name' => 'Вложенный товар', 'category_id' => $leaf->id, 'price' => 300])->assertSessionHasNoErrors();
        $this->get('/shop/category/'.$root->id)->assertOk()->assertSee('Прямой товар')->assertSee('Вложенный товар')->assertSee('Подкатегории');
        $this->get('/shop/category/'.$leaf->id)->assertOk()->assertSee('Вложенный товар')->assertDontSee('Прямой товар');
    }
}
