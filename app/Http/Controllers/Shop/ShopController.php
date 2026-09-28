<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Institution;
use App\Models\Product;

class ShopController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $categories = Category::active()->root()
            ->withCount(['products as product_count' => fn($q) => $q->where('is_active', true)->where('in_stock', true)])
            ->with(['children' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        $request->validate(['q' => 'nullable|string|max:120', 'sort' => 'nullable|in:new,price_asc,price_desc']);
        $query = Product::where('is_active', true)
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'));
        match ($request->input('sort', 'new')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query->orderByDesc('created_at'),
        };
        $featured = $query->orderBy('id')->paginate(24)->withQueryString();

        return view('shop.index', compact('categories', 'featured'));
    }

    public function category(Category $category)
    {
        abort_unless($category->is_active, 404);
        $products = Product::where('category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $categories = Category::active()->root()
            ->orderBy('sort_order')
            ->get();

        return view('shop.category', compact('category', 'products', 'categories'));
    }

    public function cart()
    {
        $institutions = Institution::where('is_active', true)
            ->orderBy('city')
            ->orderBy('name')
            ->get();

        return view('shop.cart', compact('institutions'));
    }

    public function checkout()
    {
        $institutions = Institution::where('is_active', true)
            ->orderBy('city')
            ->orderBy('name')
            ->get();

        return redirect()->route('shop.cart');
    }

    public function success()
    {
        return view('shop.success');
    }
}
