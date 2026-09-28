<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $period = (int) $request->get('period', 30);
        $period = in_array($period, [7, 30, 90]) ? $period : 30;

        $now   = Carbon::now();
        $from  = $now->copy()->subDays($period)->startOfDay();
        $prev  = $from->copy()->subDays($period)->startOfDay();

        // Current period
        $paidOrders = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$from, $now]);

        $totalOrders = $paidOrders->clone()->count();
        $revenue     = (float) $paidOrders->clone()->sum('total');
        $avgOrder    = $totalOrders > 0 ? round($revenue / $totalOrders) : 0;

        // Previous period
        $prevPaid    = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$prev, $from]);
        $prevOrders  = $prevPaid->clone()->count();
        $prevRevenue = (float) $prevPaid->clone()->sum('total');
        $prevAvg     = $prevOrders > 0 ? round($prevRevenue / $prevOrders) : 0;

        // Products
        $activeProducts = Product::where('is_active', true)->where('in_stock', true)->count();
        $newProducts    = Product::where('created_at', '>=', $now->copy()->subWeek())->count();

        // Chart data — orders per day for the period
        $days = collect();
        for ($i = $period - 1; $i >= 0; $i--) {
            $days->push($now->copy()->subDays($i)->format('Y-m-d'));
        }

        $dailyCounts = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$from, $now])
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $chartLabels = $days->map(function ($d) use ($period) {
            $dt = Carbon::parse($d);
            if ($period <= 7) {
                $days_ru = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];
                return $days_ru[$dt->dayOfWeek];
            }
            return $dt->format('d.m');
        })->values();

        $chartData = $days->map(fn($d) => (int) ($dailyCounts[$d] ?? 0))->values();

        // Recent orders
        $recentOrders = Order::latest()->limit(5)->get();

        return view('admin.report.index', compact(
            'period',
            'totalOrders', 'revenue', 'avgOrder',
            'prevOrders', 'prevRevenue', 'prevAvg',
            'activeProducts', 'newProducts',
            'chartLabels', 'chartData',
            'recentOrders'
        ));
    }
}
