<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function index()
    {
        $institutions = Institution::active()->orderBy('city')->orderBy('name')->get();

        return response()->json($institutions->map(fn($i) => [
            'id'      => $i->id,
            'name'    => $i->name,
            'city'    => $i->city,
            'address' => $i->address,
        ]));
    }
}
