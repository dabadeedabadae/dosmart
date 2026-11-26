<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        // Preserve the root response for existing clients; expose the full tree
        // and descendant IDs so native clients can filter nested products.
        $categories = Category::active()
            ->withCount(['products as product_count' => fn ($q) => $q->where('is_active', true)->where('in_stock', true)])
            ->orderBy('sort_order')->orderBy('name')->get();
        $children = $categories->groupBy('parent_id');
        $serialize = function (Category $category, array $ancestors = []) use (&$serialize, $children): array {
            $ancestors[] = $category->id;
            $nested = $children->get($category->id, collect())
                ->reject(fn ($child) => in_array($child->id, $ancestors, true))
                ->map(fn ($child) => $serialize($child, $ancestors))->values();

            return [
                'id' => $category->id, 'name' => $category->name,
                'name_kk' => $category->name_kk, 'icon' => $category->icon, 'color' => $category->color,
                'has_subcategories' => $nested->isNotEmpty(),
                'product_count' => (int) $category->product_count + $nested->sum('product_count'),
                'category_ids' => array_merge([$category->id], $nested->flatMap(fn ($child) => $child['category_ids'])->all()),
                'children' => $nested->all(),
            ];
        };

        return response()->json($categories->whereNull('parent_id')->map(fn ($category) => $serialize($category))->values());
    }
}
