<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['category_id' => 'nullable|integer|exists:categories,id']);
        $category = $request->filled('category_id') ? Category::findOrFail($request->integer('category_id')) : null;
        $products = Product::active()
            ->when($category, fn ($q) => $q->whereIn('category_id', $category->descendantIds()))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($products->map(fn($p) => [
            'id'          => $p->id,
            'category_id' => $p->category_id,
            'name'        => $p->name,
            'description' => $p->description,
            'price'       => (float) $p->price,   // float, не строка
            'unit'        => $p->unit,
            'image_url'   => $p->image_url,
            'in_stock'    => (bool) $p->in_stock,
        ]));
    }

    public function newArrivals()
    {
        $products = Product::active()
            ->latest()
            ->take(10)
            ->get();

        return response()->json($products->map(fn($p) => [
            'id'          => $p->id,
            'category_id' => $p->category_id,
            'name'        => $p->name,
            'price'       => (float) $p->price,
            'unit'        => $p->unit,
            'image_url'   => $p->image_url,
        ]));
    }
}
