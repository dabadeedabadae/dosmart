<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    private string $token;
    private string $chatId;

    public function __construct()
    {
        $this->token  = config('services.telegram.token', '');
        $this->chatId = config('services.telegram.chat_id', '');
    }

    public function newOrder(Order $order): void
    {
        if (!$this->token || !$this->chatId) {
            return;
        }

        $items = $order->items->map(
            fn($i) => "  • {$i->product_name} × {$i->quantity} — " . number_format($i->total, 0, '.', ' ') . ' ₸'
        )->join("\n");

        $squad    = $order->squad_number    ? "\n🏷 Отряд: {$order->squad_number}"        : '';
        $relative = $order->relative_name   ? "\n👤 Родственник: {$order->relative_name}"  : '';

        $text = <<<MSG
🛒 *Новый заказ {$order->order_number}*

👤 Осуждённый: {$order->prisoner_name}{$squad}
🏛 Учреждение: {$order->institution_name}
📞 Контакт: {$order->contact_phone}{$relative}

📦 Товары:
{$items}

💰 Итого: *{$order->total} ₸* (доставка: {$order->delivery_fee} ₸)
MSG;

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id'    => $this->chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram notification failed', ['error' => $e->getMessage()]);
        }
    }
}
