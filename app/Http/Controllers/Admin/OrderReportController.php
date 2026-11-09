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
                    $shopping[$key] = ['name' => $item->product_name, 'quantity' => 0, 'orders' => []];
                }
                $shopping[$key]['quantity'] += $item->quantity;
                $shopping[$key]['orders'][$order->id] = $order->order_number;
            }
        }
        usort($shopping, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        $stats = [
            'Всего заявок' => $orders->count(),
            'Оплачены' => $orders->where('payment_status', 'paid')->count(),
            'Без подтверждённой оплаты' => $orders->whereNotIn('payment_status', ['paid', 'refunded'])->where('status', '!=', 'cancelled')->count(),
            'Доставлены' => $orders->where('status', 'delivered')->count(),
            'Отменены' => $orders->where('status', 'cancelled')->count(),
            'Возвраты' => $orders->where('payment_status', 'refunded')->count(),
        ];
        return response()->view('admin.orders.report', compact('orders', 'eligible', 'selected', 'shopping', 'shoppingOrders', 'stats', 'date', 'scope', 'status', 'statuses'))
            ->header('Cache-Control', 'private, no-store');
    }
}
