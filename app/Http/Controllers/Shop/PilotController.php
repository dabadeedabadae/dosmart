<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\OrderDraft;
use App\Services\PilotCheckout;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PilotController extends Controller
{
    public function create(Request $request, PilotCheckout $service)
    {
        $terminal = $request->is('api/*');
        if ($terminal && ! $request->attributes->has('terminal_subject')) {
            $secret = (string) config('pilot.terminal_token');
            abort_unless(strlen($secret) >= 32 && hash_equals($secret, (string) $request->bearerToken()), 401);
        }
        $data = $request->validate([
            'request_id' => 'required|uuid', 'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:1000',
            'prisoner_name' => 'nullable|string|max:255',
            'institution_id' => ['nullable', 'integer', Rule::exists('institutions', 'id')->where('is_active', true)],
        ]);
        $data['items'] = array_map(fn ($item) => ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity']], $data['items']);
        $data['terminal_subject'] = $request->attributes->get('terminal_subject');
        $draft = $service->create($data, $terminal ? 'terminal' : 'website');
        $quote = $draft->order_id
            ? ['items' => $draft->order->items->toArray(), 'subtotal' => (float) $draft->order->subtotal]
            : $service->quote($draft->items);
        $url = rtrim(config('app.url'), '/').'/o/'.$draft->code;
        $lines = array_map(fn ($i) => mb_strimwidth($i['product_name'], 0, 70, '…').' × '.$i['quantity'], array_slice($quote['items'], 0, 8));
        $extra = count($quote['items']) - count($lines);
        if ($extra > 0) {
            $lines[] = '… и ещё '.$extra.' позиций';
        }
        $text = "Корзина DoSmart\n".implode("\n", $lines)."\nТовары: ".number_format($quote['subtotal'], 2, ',', ' ')." ₸\nДоставка: 3 000 ₸ на следующий день или 5 000 ₸ срочно.\nОформить: ".$url."\nКод: ".$draft->code;

        return response()->json(['code' => $draft->code, 'url' => $url, 'expires_at' => $draft->expires_at->toIso8601String(), 'subtotal' => $quote['subtotal'], 'chat_text' => $text], 201);
    }

    public function acknowledge(Request $request, string $code, PilotCheckout $service)
    {
        $draft = OrderDraft::where('code', $code)->where('terminal_subject', $request->attributes->get('terminal_subject'))->firstOrFail();
        $service->record($draft, 'chat_submitted');

        return response()->noContent();
    }

    public function lookup(Request $request)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate(['code' => 'required|regex:/^[A-Z2-9]{8}$/'], ['code.regex' => 'Введите 8 символов кода из сообщения.']);

        return redirect()->route('pilot.show', $data['code']);
    }

    private function draft(string $code): OrderDraft
    {
        $draft = OrderDraft::where('code', strtoupper($code))->firstOrFail();
        abort_if(! $draft->order_id && $draft->expires_at->isPast(), 410, 'Срок действия корзины истёк. Попросите отправить новую.');

        return $draft;
    }

    public function show(string $code, PilotCheckout $service)
    {
        $draft = $this->draft($code);
        if ($draft->order_id) {
            return redirect()->route('pilot.payment', $draft->code);
        }
        try {
            $quote = $service->quote($draft->items);
        } catch (ValidationException $e) {
            return response()->view('shop.pilot-unavailable', [], 409);
        }
        session(['pilot_return_code' => $draft->code]);
        $institutions = Institution::where('is_active', true)->orderBy('name')->get();

        return response()->view('shop.pilot-checkout', compact('draft', 'quote', 'institutions'))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }

    public function checkout(Request $request, string $code, PilotCheckout $service)
    {
        $draft = $this->draft($code);
        if ($draft->order_id) {
            return redirect()->route('pilot.payment', $draft->code);
        }
        $request->validate(['contact_phone' => 'required|string|max:30']);
        $phone = preg_replace('/[\s()+-]/', '', $request->input('contact_phone'));
        if (strlen($phone) === 11 && str_starts_with($phone, '8')) {
            $phone = '7'.substr($phone, 1);
        }
        $request->merge(['contact_phone' => '+'.$phone]);
        $data = $request->validate([
            'prisoner_name' => 'required|string|max:255',
            'institution_id' => ['required', 'integer', Rule::exists('institutions', 'id')->where('is_active', true)],
            'contact_phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{9,14}$/'],
            'delivery_type' => 'required|in:standard,urgent', 'consent' => 'accepted', 'expected_total' => 'required|numeric|min:0',
        ], ['consent.accepted' => 'Подтвердите согласие на обработку данных.', 'contact_phone.regex' => 'Укажите номер WhatsApp с кодом страны.']);
        $service->checkout($draft, $data, $request->user('customer')?->id);

        return redirect()->route('pilot.payment', $draft->code);
    }

    public function payment(string $code)
    {
        $draft = $this->draft($code);
        abort_unless($draft->order_id, 404);
        $order = $draft->order;
        // The shared link displays payment details only, never the recipient or WhatsApp number.
        $kaspiUrl = (string) config('pilot.kaspi_url');
        if (! filter_var($kaspiUrl, FILTER_VALIDATE_URL) || parse_url($kaspiUrl, PHP_URL_SCHEME) !== 'https') {
            $kaspiUrl = '';
        }
        $whatsapp = preg_replace('/\D/', '', (string) config('pilot.whatsapp'));

        return response()->view('shop.pilot-payment', compact('order', 'kaspiUrl', 'whatsapp'))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }
}
