<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TerminalSubmission;
use Illuminate\Http\Request;

class TerminalOrderController extends Controller
{
    public function store(Request $request, TerminalSubmission $service)
    {
        $request->validate(['contact_phone' => 'nullable|string|max:30']);
        if ($request->filled('contact_phone')) {
        $phone = preg_replace('/[\s()+-]/', '', $request->input('contact_phone'));
        if (strlen($phone) === 11 && str_starts_with($phone, '8')) {
            $phone = '7'.substr($phone, 1);
        }
        $request->merge(['contact_phone' => '+'.$phone]);
        }
        $data = $request->validate([
            'request_id' => 'required|uuid',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'prisoner_name' => 'required|string|min:3|max:255',
            'institution_name' => 'required|string|min:2|max:255',
            'contact_phone' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{9,14}$/'],
            'consent' => 'required|accepted',
        ]);
        $data['items'] = array_map(fn ($item) => ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity']], $data['items']);
        $order = $service->submit($data, $request->attributes->get('terminal_subject'));

        // No shared payment link or personal information is returned to the terminal.
        return response()->json(['order_number' => $order->order_number, 'status' => $order->status, 'subtotal' => $order->subtotal], 201)->header('Cache-Control', 'no-store');
    }
}
