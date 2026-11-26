<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['category_id' => 'nullable|integer|exists:categories,id', 'q' => 'nullable|string|max:120']);
        $category = $request->filled('category_id') ? Category::findOrFail($request->integer('category_id')) : null;
        $products = Product::with('category')
            ->when($category, fn ($q) => $q->whereIn('category_id', $category->descendantIds()))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::active()->with('parent')->get()->sortBy('path_name'),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.products.form', [
            'listContext' => $this->listContext($request),
            'categories' => Category::active()->with('parent')->get()->sortBy('path_name'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|max:4096',
        ]);

        $data['in_stock'] = $request->boolean('in_stock', true);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('photo')) {
            $data['image_url'] = Storage::url($request->file('photo')->store('products', 'public'));
        }

        Product::create($data);

        return redirect()->route('admin.products.index', $this->listContext($request))->with('success', 'Товар добавлен.');
    }

    public function edit(Request $request, Product $product)
    {
        return view('admin.products.form', [
            'listContext' => $this->listContext($request),
            'product' => $product,
            'categories' => Category::active()->with('parent')->get()->sortBy('path_name'),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|max:4096',
            'remove_photo' => 'nullable|boolean',
        ]);

        $data['in_stock'] = $request->boolean('in_stock');
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('photo')) {
            $this->deleteStoredPhoto($product->image_url);
            $data['image_url'] = Storage::url($request->file('photo')->store('products', 'public'));
        } elseif ($request->boolean('remove_photo')) {
            $this->deleteStoredPhoto($product->image_url);
            $data['image_url'] = null;
        }

        $product->update($data);

        return redirect()->route('admin.products.index', $this->listContext($request))->with('success', 'Товар обновлён.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->deleteStoredPhoto($product->image_url);
        $product->delete();

        return redirect()->route('admin.products.index', $this->listContext($request))->with('success', 'Товар удалён.');
    }

    private function listContext(Request $request): array
    {
        $input = $request->input('list', []);
        if (! is_array($input)) return [];
        $context = [];
        foreach (['category_id', 'page'] as $key) {
            $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($value && ($key !== 'category_id' || Category::whereKey($value)->exists())) $context[$key] = $value;
        }
        if (isset($input['q']) && is_string($input['q']) && mb_strlen($input['q']) <= 120) $context['q'] = $input['q'];
        return $context;
    }

    private function deleteStoredPhoto(?string $imageUrl): void
    {
        if (! $imageUrl || ! str_starts_with($imageUrl, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(substr($imageUrl, strlen('/storage/')));
    }
}
