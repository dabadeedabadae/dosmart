<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class OrderReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'scope' => 'nullable|in:created,activity',
            'status' => 'nullable|in:new,pending,paid,checking,processing,delivering,delivered,cancelled,refunded',
            'orders' => 'nullable|array|max:500',
            'orders.*' => 'integer|distinct|min:1',
        ]);
        $date = $data['date'] ?? CarbonImmutable::now('Asia/Almaty')->format('Y-m-d');
        $start = CarbonImmutable::parse($date, 'Asia/Almaty')->startOfDay()->utc();
        $end = $start->addDay();
        $scope = $data['scope'] ?? 'created';
        $status = $data['status'] ?? '';
        $statuses = ['new'=>'Новая заявка', 'pending'=>'Ожидает оплаты', 'paid'=>'Оплачен', 'checking'=>'На проверке', 'processing'=>'Собирается', 'delivering'=>'В доставке', 'delivered'=>'Доставлено', 'cancelled'=>'Отменён', 'refunded'=>'Возврат'];
        $query = Order::query();
        if ($status !== '') $query->where('status', $status);
        if ($scope === 'activity') {
            $query->whereHas('statusHistory', fn ($q) => $q->where('created_at', '>=', $start)->where('created_at', '<', $end));
        } else {
            $query->where('created_at', '>=', $start)->where('created_at', '<', $end);
        }
        $orders = $query->with(['items', 'statusHistory' => fn ($q) => $q->where('created_at', '>=', $start)->where('created_at', '<', $end)])->latest()->get();
        $eligible = $orders;
        $selected = collect($data['orders'] ?? [])->map(fn ($id) => (int) $id)->intersect($eligible->pluck('id'))->values();
        $shoppingOrders = $eligible->whereIn('id', $selected->all());
        $shopping = [];
        foreach ($shoppingOrders as $order) {
            foreach ($order->items as $item) {
                // Different products and different historical names must not be merged accidentally.
                $key = ($item->product_id ?? 'deleted').':'.$item->product_name;
                if (!isset($shopping[$key])) {
                    $shopping[$key] = ['name' => $item->product_name, 'quantity' => 0, 'orders' => [], 'prices' => [], 'total' => 0.0];
                }
                $shopping[$key]['quantity'] += $item->quantity;
                // Цена в заявке — цена магазина на момент заказа; у одного товара в разных заявках она может отличаться.
                $shopping[$key]['prices'][(string) $item->price] = (float) $item->price;
                $shopping[$key]['total'] += (float) $item->total;
                $shopping[$key]['orders'][$order->id] = $order->order_number;
            }
        }
        usort($shopping, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        $shoppingTotals = ['quantity' => array_sum(array_column($shopping, 'quantity')), 'total' => array_sum(array_column($shopping, 'total')), 'positions' => count($shopping)];
        $active = $orders->whereNotIn('status', ['cancelled', 'refunded']);
        $money = [
            'Сумма заявок (без отменённых)' => $active->sum(fn ($o) => (float) $o->total),
            'Из них товары' => $active->sum(fn ($o) => (float) $o->subtotal),
            'Из них доставка' => $active->sum(fn ($o) => (float) $o->delivery_fee),
            'Оплачено (подтверждено)' => $orders->where('payment_status', 'paid')->sum(fn ($o) => (float) $o->total),
            'Ждём оплату' => $active->whereNotIn('payment_status', ['paid', 'refunded'])->sum(fn ($o) => (float) $o->total),
            'Возвращено' => $orders->sum(fn ($o) => (float) ($o->refund_amount ?? 0)),
        ];
        $stats = [
            'Всего заявок' => $orders->count(),
            'Оплачены' => $orders->where('payment_status', 'paid')->count(),
            'Без подтверждённой оплаты' => $orders->whereNotIn('payment_status', ['paid', 'refunded'])->where('status', '!=', 'cancelled')->count(),
            'Доставлены' => $orders->where('status', 'delivered')->count(),
            'Отменены' => $orders->where('status', 'cancelled')->count(),
            'Возвраты' => $orders->where('payment_status', 'refunded')->count(),
            'Товаров, шт.' => $active->sum(fn ($o) => $o->items->sum('quantity')),
        ];
        return response()->view('admin.orders.report', compact('orders', 'eligible', 'selected', 'shopping', 'shoppingOrders', 'shoppingTotals', 'money', 'stats', 'date', 'scope', 'status', 'statuses'))
            ->header('Cache-Control', 'private, no-store');
    }
}
