<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $data = $this->validated($request);
        if (array_key_exists('order_code', $data) && $data['order_code'] !== $institution->order_code && $institution->orders()->whereNotNull('display_number')->exists()) {
            throw ValidationException::withMessages(['order_code' => 'Этот код уже используется в номерах заказов и не может быть изменён.']);
        }
        $institution->update($data);

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
        if ($institution->orders()->exists() || $institution->drafts()->exists() || $institution->prisoners()->exists()) {
            return redirect()->route('admin.institutions.index')->with('error', "Нельзя удалить учреждение «{$institution->name}» — с ним связаны заказы или черновики. Вместо удаления отключите его активность.");
        }
        $institution->delete();

        return redirect()->route('admin.institutions.index')->with('success', 'Учреждение удалено.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'order_code' => ['sometimes', 'nullable', 'regex:/^(0[1-9]|[1-9][0-9])$/', Rule::unique('institutions', 'order_code')->ignore($request->route('institution'))],
            'city' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
