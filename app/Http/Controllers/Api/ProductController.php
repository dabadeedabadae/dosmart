<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::active()
            ->when($request->category_id, fn($q) => $q->where('category_id', (int) $request->category_id))
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
