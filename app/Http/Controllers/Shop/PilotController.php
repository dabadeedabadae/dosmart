<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Order;
use App\Models\OrderDraft;
use App\Models\Prisoner;
use App\Models\Product;
use App\Services\OrderChanges;
use App\Services\OrderPaymentLink;
use App\Services\PilotCheckout;
use App\Services\PrisonerAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PilotController extends Controller
{
    public function create(Request $request, PilotCheckout $service)
    {
        $guest = $request->routeIs('guest.drafts');
        $terminal = $request->is('api/*') && ! $guest;
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
        $data['terminal_subject'] = $terminal ? $request->attributes->get('terminal_subject') : null;
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
        $text = "Корзина DoSmart\n".implode("\n", $lines)."\nТовары: ".number_format($quote['subtotal'], 2, ',', ' ')." ₸\nДоставка: 3 000 ₸ на следующий день.\nОформить: ".$url."\nКод: ".$draft->code;

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

    /**
     * Лимит получателя для родственника, который собирает корзину: только остаток, без других данных.
     * Работает, когда у учреждения заданы нормы передач.
     */
    public function recipientLimits(Request $request, string $code, PrisonerAccounts $accounts)
    {
        $draft = $this->draft($code);
        abort_if($draft->order_id, 404);
        $data = $request->validate([
            'institution_id' => 'required|integer',
            'prisoner_name' => 'required|string|min:3|max:255',
        ]);
        $institution = Institution::whereKey($data['institution_id'])->where('is_active', true)->first();
        if (! $institution || ! $institution->requiresParcelCode()) {
            return response()->json(['limited' => false])->header('Cache-Control', 'private, no-store');
        }
        $found = $accounts->recipientByName($institution->id, $data['prisoner_name']);
        if ($found->count() !== 1) {
            return response()->json(['limited' => true, 'found' => false, 'namesakes' => $found->count() > 1])->header('Cache-Control', 'private, no-store');
        }
        $prisoner = Prisoner::with('institution')->findOrFail($found->first()->id);

        return response()->json(['limited' => true, 'found' => true] + $accounts->recipientLimit($prisoner))
            ->header('Cache-Control', 'private, no-store');
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
        } elseif (strlen($phone) === 10) {
            $phone = '7'.$phone;
        }
        $request->merge(['contact_phone' => '+'.$phone]);
        $data = $request->validate([
            'squad_number' => 'nullable|string|max:50',
            'prisoner_name' => 'required|string|max:255',
            'relative_name' => 'required|string|min:3|max:255',
            'institution_id' => ['required', 'integer', Rule::exists('institutions', 'id')->where('is_active', true)],
            'contact_phone' => ['required', 'string', 'regex:/^\+7[0-9]{10}$/'],
            'delivery_type' => 'required|in:standard', 'consent' => 'accepted', 'expected_total' => 'required|numeric|min:0',
        ], ['consent.accepted' => 'Подтвердите согласие на обработку данных.', 'contact_phone.regex' => 'Укажите номер WhatsApp в формате +7 705 621 8748.', 'relative_name.required' => 'Укажите ваши ФИО — они будут в чеке как отправитель.']);
        $service->checkout($draft, $data, $request->user('customer')?->id);

        return redirect()->route('pilot.payment', $draft->code);
    }

    public function reportPayment(Request $request, string $code)
    {
        $draft = $this->draft($code);
        abort_unless($draft->order_id, 404);
        DB::transaction(function () use ($draft, $request) {
            $order = Order::whereKey($draft->order_id)->lockForUpdate()->firstOrFail();
            if ($order->changeRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['changes' => 'Сначала дождитесь согласования изменений.']);
            }
            if ($request->filled('revision') && ! hash_equals(app(OrderChanges::class)->revision($order), (string) $request->input('revision'))) {
                throw ValidationException::withMessages(['changes' => 'Заявка изменилась. Проверьте актуальную сумму и статус.']);
            }
            // A customer's report never confirms a payment or changes its amount.
            if ($order->status !== 'pending' || $order->payment_reported_at) {
                return;
            }
            $order->update(['payment_reported_at' => now()]);
            $order->statusHistory()->create([
                'status' => 'pending',
                'comment' => 'Клиент сообщил об оплате. Требуется сверка в Kaspi Pay.',
            ]);
        });

        return redirect()->route('pilot.payment', $draft->code);
    }

    public function requestChanges(Request $request, string $code, OrderChanges $changes)
    {
        $draft = $this->draft($code);
        abort_unless($draft->order_id, 404);
        $data = $request->validate([
            'revision' => 'required|string|size:64',
            'items' => 'required|array|min:1|max:100',
            'items.*.id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:0|max:1000',
            'additions' => 'sometimes|array|max:100',
            'additions.*.product_id' => 'required|integer|distinct',
            'additions.*.quantity' => 'required|integer|min:1|max:1000',
            'additions.*.price' => 'required|numeric|min:0.01',
            'comment' => 'nullable|string|max:1000',
        ]);
        $changes->request($draft->order, $data);

        return redirect()->route('pilot.payment', $draft->code)->with('success', 'Запрос изменений отправлен сотруднику.');
    }

    private function pageData(Order $order): array
    {
        $order->load(['items.product', 'changeRequests', 'statusHistory']);
        $links = app(OrderPaymentLink::class);
        $kaspiUrl = $links->forOrder($order);
        $replacement = $links->replacement($order);
        $revision = app(OrderChanges::class)->revision($order);
        $pendingChange = $order->changeRequests->where('status', 'pending')->first();

        return ['order' => $order, 'kaspiUrl' => $kaspiUrl,
            'catalogProducts' => app(OrderChanges::class)->editable($order) && ! $pendingChange
                ? Product::active()->with('category')->whereNotIn('id', $order->items->pluck('product_id')->filter())->orderBy('name')->get() : collect(),
            'whatsapp' => preg_replace('/\D/', '', (string) config('pilot.whatsapp')),
            'revision' => $revision,
            'screenRevision' => $replacement ? hash('sha256', $revision.'|'.$replacement) : $revision,
            'paymentLinkChanged' => $replacement !== '',
            'pendingChange' => $pendingChange,
            'editable' => app(OrderChanges::class)->editable($order) && ! $pendingChange,
        ];
    }

    public function status(Request $request, string $code)
    {
        $draft = $this->draft($code);
        abort_unless($draft->order_id, 404);
        $data = $this->pageData($draft->order);
        $result = ['status' => $draft->order->status, 'label' => $data['pendingChange'] ? 'Согласование изменений' : $draft->order->customer_status_label, 'revision' => $data['screenRevision']];
        if ($request->has('revision') && $request->input('revision') !== $data['screenRevision']) {
            $result['html'] = view('shop.partials.order-details', $data)->render();
        }

        return response()->json($result)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }

    public function receipt(string $code)
    {
        $draft = $this->draft($code);
        abort_unless($draft->order_id, 404);
        $order = $draft->order->load('items');

        return response()->view('admin.orders.receipt', ['order' => $order, 'publicReceipt' => true])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function payment(string $code)
    {
        $draft = $this->draft($code);
        abort_unless($draft->order_id, 404);

        return response()->view('shop.pilot-payment', $this->pageData($draft->order))
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }
}
