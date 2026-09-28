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
        $products = Product::with('category')
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->paginate(30);

        return view('admin.products.index', [
            'products'   => $products,
            'categories' => Category::active()->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.products.form', [
            'categories' => Category::active()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price'       => 'required|numeric|min:0',
            'unit'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'photo'       => 'nullable|image|max:4096',
        ]);

        $data['in_stock']  = $request->boolean('in_stock', true);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('photo')) {
            $data['image_url'] = Storage::url($request->file('photo')->store('products', 'public'));
        }

        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Товар добавлен.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product'    => $product,
            'categories' => Category::active()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price'       => 'required|numeric|min:0',
            'unit'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'photo'       => 'nullable|image|max:4096',
            'remove_photo' => 'nullable|boolean',
        ]);

        $data['in_stock']  = $request->boolean('in_stock');
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('photo')) {
            $this->deleteStoredPhoto($product->image_url);
            $data['image_url'] = Storage::url($request->file('photo')->store('products', 'public'));
        } elseif ($request->boolean('remove_photo')) {
            $this->deleteStoredPhoto($product->image_url);
            $data['image_url'] = null;
        }

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Товар обновлён.');
    }

    public function destroy(Product $product)
    {
        $this->deleteStoredPhoto($product->image_url);
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Товар удалён.');
    }

    private function deleteStoredPhoto(?string $imageUrl): void
    {
        if (!$imageUrl || !str_starts_with($imageUrl, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(substr($imageUrl, strlen('/storage/')));
    }
}
