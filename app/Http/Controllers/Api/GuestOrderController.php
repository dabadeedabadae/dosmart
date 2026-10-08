<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Services\TerminalSubmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestOrderController extends Controller
{
    public function store(Request $request, TerminalSubmission $service)
    {
        $request->validate(['contact_phone' => 'required|string|max:30']);
        $phone = preg_replace('/[\s()+-]/', '', $request->input('contact_phone'));
        if (strlen($phone) === 11 && str_starts_with($phone, '8')) {
            $phone = '7'.substr($phone, 1);
        }
        $request->merge(['contact_phone' => '+'.$phone]);
        $data = $request->validate([
            'request_id' => 'required|uuid',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'prisoner_name' => 'required|string|min:3|max:255',
            'institution_id' => ['required', 'integer', Rule::exists('institutions', 'id')->where('is_active', true)],
            'contact_phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{9,14}$/'],
            'consent' => 'required|accepted',
        ]);
        $data['institution_name'] = Institution::findOrFail($data['institution_id'])->name;
        $data['institution_id'] = (int) $data['institution_id'];
        $data['items'] = array_map(fn ($item) => ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity']], $data['items']);
        // Public guest request, not a verified Soylephone identity. The random
        // client UUID isolates retries; neither credentials nor PII are returned.
        $order = $service->submit($data, 'guest:'.hash('sha256', $data['request_id']), 'website');

        return response()->json([
            'order_number' => $order->order_number, 'status' => $order->status, 'subtotal' => $order->subtotal,
        ], 201)->header('Cache-Control', 'no-store');
    }
}
