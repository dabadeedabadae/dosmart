<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        // withCount в одном запросе вместо N запросов COUNT в цикле
        $categories = Category::active()
            ->root()
            ->withCount(['products as product_count' => fn($q) => $q->where('is_active', true)->where('in_stock', true)])
            ->with(['children' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories->map(fn($cat) => [
            'id'                => $cat->id,
            'name'              => $cat->name,
            'name_kk'           => $cat->name_kk,
            'icon'              => $cat->icon,
            'color'             => $cat->color,
            'has_subcategories' => $cat->children->isNotEmpty(),
            'product_count'     => (int) $cat->product_count,
        ]));
    }
}
