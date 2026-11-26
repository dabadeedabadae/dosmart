<?php

namespace App\Services;

use App\Models\Order;

class OrderPaymentLink
{
    public function replacement(Order $order): string
    {
        if ($order->status !== 'pending' || $order->payment_status !== 'unpaid' || $order->payment_reported_at
            || in_array($order->order_number, config('pilot.kaspi_protected_orders', []), true)) {
            return '';
        }

        return $this->httpsUrl((string) config('pilot.kaspi_replacement_url'));
    }

    public function forOrder(Order $order): string
    {
        return $this->replacement($order) ?: $this->httpsUrl((string) config('pilot.kaspi_url'));
    }

    private function httpsUrl(string $url): string
    {
        return filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : '';
    }
}
