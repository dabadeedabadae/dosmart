<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function index()
    {
        $institutions = Institution::withCount('orders')->orderBy('city')->orderBy('name')->get();

        return view('admin.institutions.index', compact('institutions'));
    }

    public function create()
    {
        return view('admin.institutions.form');
    }

    public function store(Request $request)
    {
        Institution::create($this->validated($request));

        return redirect()->route('admin.institutions.index')->with('success', 'Учреждение добавлено.');
    }

    public function edit(Institution $institution)
    {
        return view('admin.institutions.form', compact('institution'));
    }

    public function update(Request $request, Institution $institution)
    {
        $institution->update($this->validated($request));

        return redirect()->route('admin.institutions.index')->with('success', 'Учреждение обновлено.');
    }

    public function updateActivity(Request $request, Institution $institution)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $institution->update($data);

        return redirect()->route('admin.institutions.index')->with('success', 'Активность учреждения обновлена.');
    }

    public function destroy(Institution $institution)
    {
        if ($institution->orders()->exists() || $institution->drafts()->exists()) {
            return redirect()->route('admin.institutions.index')->with('error', "Нельзя удалить учреждение «{$institution->name}» — с ним связаны заказы или черновики. Вместо удаления отключите его активность.");
        }
        $institution->delete();

        return redirect()->route('admin.institutions.index')->with('success', 'Учреждение удалено.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
