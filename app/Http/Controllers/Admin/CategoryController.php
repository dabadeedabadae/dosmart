<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'name_kk'    => 'nullable|string|max:255',
            'icon'       => 'nullable|string|max:100',
            'color'      => 'nullable|string|regex:/^#[0-9A-Fa-f]{3,6}$/',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        // color_text (текстовое поле) имеет приоритет над color picker
        if ($request->filled('color_text')) {
            $data['color'] = $request->input('color_text');
        }

        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Категория добавлена.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'name_kk'    => 'nullable|string|max:255',
            'icon'       => 'nullable|string|max:100',
            'color'      => 'nullable|string|regex:/^#[0-9A-Fa-f]{3,6}$/',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        if ($request->filled('color_text')) {
            $data['color'] = $request->input('color_text');
        }

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Категория обновлена.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0) {
            return redirect()->route('admin.categories.index')
                ->with('error', "Нельзя удалить категорию «{$category->name}» — в ней есть товары. Сначала перенесите или удалите товары.");
        }

        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Категория удалена.');
    }
}
