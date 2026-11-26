<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderNumbers
{
    public function next(?int $institutionId): ?string
    {
        if (! $institutionId) return null;
        if (DB::transactionLevel() === 0) throw new \LogicException('Allocate order numbers inside the order transaction.');
        // Serialize allocation per institution, including concurrent checkouts.
        $institution = Institution::whereKey($institutionId)->lockForUpdate()->firstOrFail();
        if (! $institution->order_code) return null;
        $prefix = $institution->order_code.now('Asia/Almaty')->format('m');
        $used = Order::where('display_number', 'like', $prefix.'%')->pluck('display_number')->all();
        // Include previous years: a short number must never identify two orders.
        $taken = array_fill_keys($used, true);
        $available = [];
        for ($i = 0; $i <= 999; $i++) {
            $number = $prefix.str_pad($i, 3, '0', STR_PAD_LEFT);
            if (! isset($taken[$number])) $available[] = $number;
        }
        if ($available) return $available[random_int(0, count($available) - 1)];
        throw ValidationException::withMessages(['items' => 'Лимит номеров учреждения на этот месяц исчерпан. Свяжитесь с магазином.']);
    }
}
