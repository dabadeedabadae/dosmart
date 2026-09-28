<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Order;
use App\Models\OrderDraft;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PilotCheckout
{
    public function quote(array $items): array
    {
        $products = Product::active()->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
        $lines = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['items' => 'Один из товаров больше недоступен. Соберите новую корзину.']);
            }
            $cents = (int) round((float) $product->price * 100);
            $line = $cents * $item['quantity'];
            $subtotal += $line;
            $lines[] = ['product_id' => $product->id, 'product_name' => $product->name, 'price' => $cents / 100, 'quantity' => $item['quantity'], 'total' => $line / 100];
        }
        if ($subtotal > 9000000000) {
            throw ValidationException::withMessages(['items' => 'Сумма заказа слишком велика.']);
        }

        return ['items' => $lines, 'subtotal' => $subtotal / 100];
    }

    public function record(OrderDraft $draft, string $event): void
    {
        DB::table('pilot_events')->insertOrIgnore(['request_id' => $draft->request_id, 'event' => $event, 'source' => $draft->source, 'created_at' => now()]);
    }

    public function create(array $data, string $source): OrderDraft
    {
        // A repeated request returns the same draft; a code is never an incrementing ID.
        $existing = OrderDraft::where('request_id', $data['request_id'])->first();
        if ($existing) {
            abort_unless($existing->source === $source && $existing->terminal_subject === ($data['terminal_subject'] ?? null) && $existing->items === $data['items'], 409, 'Для изменённой корзины используйте новый request_id.');
            abort_if(! $existing->order_id && $existing->expires_at->isPast(), 410, 'Срок действия корзины истёк.');

            return $existing;
        }
        $this->quote($data['items']);

        return DB::transaction(function () use ($data, $source) {
            $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $draft = OrderDraft::createOrFirst(['request_id' => $data['request_id']], [
                'code' => $code, 'source' => $source, 'terminal_subject' => $data['terminal_subject'] ?? null, 'items' => $data['items'],
                'prisoner_name' => $data['prisoner_name'] ?? null, 'institution_id' => $data['institution_id'] ?? null,
                'expires_at' => now()->addDays(config('pilot.draft_days')),
            ]);
            abort_unless($draft->source === $source && $draft->terminal_subject === ($data['terminal_subject'] ?? null) && $draft->items === $data['items'], 409, 'Для изменённой корзины используйте новый request_id.');
            $this->record($draft, 'draft_created');

            return $draft;
        });
    }

    public function checkout(OrderDraft $draft, array $data, ?int $customerId): Order
    {
        return DB::transaction(function () use ($draft, $data, $customerId) {
            $draft = OrderDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($draft->order_id) {
                return $draft->order;
            }
            abort_if($draft->expires_at->isPast(), 410, 'Срок действия корзины истёк.');
            $quote = $this->quote($draft->items);
            $fee = $data['delivery_type'] === 'urgent' ? 5000 : 3000;
            // Reject a stale total instead of silently changing the amount agreed by the customer.
            if ((int) round($data['expected_total'] * 100) !== (int) round(($quote['subtotal'] + $fee) * 100)) {
                throw ValidationException::withMessages(['items' => 'Цена товаров изменилась. Обновите страницу и проверьте новую сумму.']);
            }
            $institution = Institution::where('is_active', true)->findOrFail($data['institution_id']);
            $order = Order::create([
                'order_number' => 'DOS-'.$draft->code, 'customer_id' => $customerId,
                'prisoner_name' => $data['prisoner_name'], 'institution_id' => $institution->id,
                'institution_name' => $institution->name, 'contact_phone' => $data['contact_phone'],
                'subtotal' => $quote['subtotal'], 'delivery_fee' => $fee, 'total' => $quote['subtotal'] + $fee,
                'delivery_type' => $data['delivery_type'], 'consented_at' => now(), 'consent_version' => config('pilot.consent_version'),
                'status' => 'pending', 'payment_status' => 'unpaid',
            ]);
            $order->items()->createMany($quote['items']);
            $order->statusHistory()->create(['status' => 'pending', 'comment' => 'Оформлен родственником. Согласие: '.config('pilot.consent_version')]);
            $draft->update(['order_id' => $order->id]);
            $this->record($draft, 'checkout_completed');

            return $order;
        });
    }
}
