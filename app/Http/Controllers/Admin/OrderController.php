<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDraft;
use App\Services\OrderChanges;
use App\Services\PilotCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private array $statuses = ['new' => 'Новая заявка', 'pending' => 'Ожидает оплаты', 'paid' => 'Оплачен', 'checking' => 'На проверке', 'processing' => 'Собирается', 'delivering' => 'В доставке', 'delivered' => 'Доставлено', 'refunded' => 'Возврат', 'cancelled' => 'Отменён'];

    private function listContext(Request $request): array
    {
        $input = $request->input('list', []);
        if (!is_array($input)) return [];
        $result = [];
        if (is_string($input['q'] ?? null)) $result['q'] = mb_substr($input['q'], 0, 120);
        if (is_string($input['status'] ?? null) && isset($this->statuses[$input['status']])) $result['status'] = $input['status'];
        if (filter_var($input['page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) $result['page'] = (int)$input['page'];
        return $result;
    }

    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:120', 'status' => 'nullable|in:'.implode(',', array_keys($this->statuses)), 'page' => 'nullable|integer|min:1']);
        $orders = Order::withCount(['items', 'changeRequests as pending_changes_count' => fn ($q) => $q->where('status', 'pending')])->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($search) => $search->where('order_number', 'like', '%'.$request->string('q').'%')->orWhere('display_number', 'like', '%'.$request->string('q').'%')))->latest()->paginate(20);
        $metrics = DB::table('pilot_events')->where('source', 'terminal')->selectRaw('event, count(*) as count')->groupBy('event')->pluck('count', 'event');
        $drafts = OrderDraft::whereNull('order_id')->where('expires_at', '>', now())->latest()->limit(20)->get();

        return view('admin.orders.index', ['orders' => $orders, 'statuses' => $this->statuses, 'metrics' => $metrics, 'drafts' => $drafts]);
    }

    public function show(Order $order)
    {
        $order->load(['items', 'statusHistory']);

        return view('admin.orders.show', ['order' => $order, 'statuses' => $this->statuses, 'listContext' => $this->listContext(request())]);
    }

    public function receipt(Order $order)
    {
        $order->load('items');

        return response()->view('admin.orders.receipt', compact('order'))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function reviewChanges(Request $request, Order $order, int $change, OrderChanges $service)
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'response' => 'nullable|string|max:500']);
        $service->review($order, $change, $data, $request->user()->id);

        return redirect()->route('admin.orders.show', ['order' => $order, 'list' => $this->listContext($request)])->with('success', 'Ответ на изменения сохранён.');
    }

    public function updateStatus(Request $request, Order $order, PilotCheckout $service)
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys($this->statuses)),
            'delivery_type' => 'nullable|in:standard',
            'comment' => 'nullable|string|max:180',
            'payment_verified' => 'sometimes|accepted',
            'payment_reference' => 'nullable|string|max:100',
            'refund_amount' => 'nullable|numeric|min:0.01',
            'refund_verified' => 'sometimes|accepted',
        ]);
        DB::transaction(function () use ($request, $order, $data, $service) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $next = $data['status'];
            if (in_array($next, ['pending', 'paid'], true) && $order->changeRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['changes' => 'Сначала рассмотрите запрос изменений от родственника.']);
            }
            if ($next === 'cancelled') {
                $order->changeRequests()->where('status', 'pending')->update(['status' => 'rejected', 'response' => 'Заявка отменена сотрудником.', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            }
            if ($next === $order->status && $next !== 'refunded') {
                return;
            }
            $allowed = [
                'new' => ['pending', 'cancelled'],
                'pending' => ['paid', 'cancelled'], 'paid' => ['checking', 'processing', 'delivering', 'refunded'],
                'checking' => ['processing', 'delivering', 'refunded'], 'processing' => ['delivering', 'refunded'],
                'delivering' => ['delivered', 'refunded'], 'delivered' => ['refunded'], 'refunded' => ['refunded'], 'cancelled' => [],
            ];
            if (! in_array($next, $allowed[$order->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Недопустимый переход статуса. Сначала подтвердите оплату; оплаченные заказы отменяются через возврат.']);
            }
            $changes = ['status' => $next];
            $comment = $data['comment'] ?? $this->statuses[$next];
            if ($order->status === 'new' && $next === 'pending') {
                if (empty($data['delivery_type'])) {
                    throw ValidationException::withMessages(['delivery_type' => 'Согласуйте с родственником и выберите доставку.']);
                }
                $fee = (float) config('pilot.delivery_fee', 3000);
                $changes += ['delivery_type' => $data['delivery_type'], 'delivery_fee' => $fee, 'total' => (float) $order->subtotal + $fee];
                $comment .= ' · Доставка согласована, ссылка на оплату подготовлена';
            }
            if ($next === 'paid') {
                if (! $request->boolean('payment_verified') || empty($data['payment_reference'])) {
                    throw ValidationException::withMessages(['payment_verified' => 'Сверьте сумму, время и комментарий в Kaspi Pay. Укажите идентификатор платежа и подтвердите проверку.']);
                }
                $changes += ['payment_status' => 'paid', 'paid_at' => now(), 'kaspi_transaction_id' => $data['payment_reference']];
                $comment .= ' · Платёж проверен в Kaspi Pay';
            }
            if ($next === 'refunded') {
                $amount = (float) ($data['refund_amount'] ?? 0);
                if (! $request->boolean('refund_verified') || empty($data['comment']) || $amount <= (float) $order->refund_amount || $amount > (float) $order->total) {
                    throw ValidationException::withMessages(['refund_amount' => 'Сначала выполните возврат в Kaspi Pay. Укажите причину и общую возвращённую сумму: больше предыдущей и не больше суммы заказа.']);
                }
                $changes += ['payment_status' => 'refunded', 'refund_amount' => $amount];
                $comment .= ' · Возвращено всего: '.$amount.' ₸';
            }
            if ($next === 'delivered') {
                $changes['delivered_at'] = now();
            }
            $order->update($changes);
            $order->statusHistory()->create(['status' => $next, 'comment' => mb_substr(($request->user()->username ?? 'admin').': '.$comment, 0, 255)]);
            if ($next === 'paid' && ($draft = OrderDraft::where('order_id', $order->id)->first())) {
                $service->record($draft, 'payment_confirmed');
            }
        });

        return redirect()->route('admin.orders.show', ['order' => $order, 'list' => $this->listContext($request)])->with('success', 'Статус обновлён.');
    }
}
