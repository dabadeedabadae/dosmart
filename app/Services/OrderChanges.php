<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderChanges
{
    public function editable(Order $order): bool
    {
        return in_array($order->status, ['new', 'pending'], true)
            && $order->payment_status === 'unpaid' && ! $order->payment_reported_at;
    }

    public function revision(Order $order): string
    {
        return hash('sha256', json_encode([$order->status, $order->payment_status,
            $order->payment_reported_at, $order->total, $order->refund_amount, $order->delivery_type,
            $order->items()->orderBy('id')->get(['id', 'quantity', 'price'])->toArray(),
            $order->changeRequests()->orderBy('id')->get(['id', 'status', 'response'])->toArray()]));
    }

    public function request(Order $order, array $data): void
    {
        DB::transaction(function () use ($order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $this->editable($order) || $order->changeRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['changes' => 'Сейчас изменения недоступны: дождитесь ответа сотрудника или свяжитесь с ним в WhatsApp.']);
            }
            if (! hash_equals($this->revision($order), $data['revision'])) {
                throw ValidationException::withMessages(['changes' => 'Заявка изменилась. Обновите страницу перед отправкой.']);
            }
            $existing = $order->items()->get()->keyBy('id');
            $items = [];
            $changed = false;
            foreach ($data['items'] as $line) {
                $item = $existing->get($line['id']);
                if (! $item) {
                    throw ValidationException::withMessages(['changes' => 'Товар не относится к этой заявке.']);
                }
                $qty = (int) $line['quantity'];
                $changed = $changed || $qty !== $item->quantity;
                $items[] = ['id' => $item->id, 'name' => $item->product_name, 'before' => $item->quantity, 'quantity' => $qty];
            }
            if (count($items) !== $existing->count()) {
                throw ValidationException::withMessages(['changes' => 'Обновите страницу: передан неполный состав заявки.']);
            }
            foreach ($data['additions'] ?? [] as $line) {
                $product = Product::active()->find($line['product_id']);
                if (! $product || $existing->contains('product_id', $product->id)) {
                    throw ValidationException::withMessages(['changes' => 'Новый товар недоступен или уже есть в заявке.']);
                }
                if ((int) round($line['price'] * 100) !== (int) round($product->price * 100)) {
                    throw ValidationException::withMessages(['changes' => 'Цена товара изменилась. Обновите страницу и проверьте сумму.']);
                }
                $items[] = ['id' => null, 'product_id' => $product->id, 'name' => $product->name,
                    'price' => $product->price, 'before' => 0, 'quantity' => (int) $line['quantity']];
                $changed = true;
            }
            if (count($items) > 100 || array_sum(array_column($items, 'quantity')) === 0) {
                throw ValidationException::withMessages(['changes' => 'Оставьте хотя бы один товар. Для отмены всей заявки свяжитесь с сотрудником.']);
            }
            if (! $changed && empty($data['comment'])) {
                throw ValidationException::withMessages(['changes' => 'Измените количество или напишите пожелание.']);
            }
            $order->changeRequests()->create(['items' => $items, 'comment' => $data['comment'] ?? null]);
            $order->statusHistory()->create(['status' => $order->status, 'comment' => 'Родственник запросил изменение состава. Оплата приостановлена до ответа сотрудника.']);
        });
    }

    public function review(Order $order, int $changeId, array $data, int $userId): void
    {
        DB::transaction(function () use ($order, $changeId, $data, $userId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $change = $order->changeRequests()->whereKey($changeId)->firstOrFail();
            if ($change->status !== 'pending') {
                return;
            }
            if (! $this->editable($order)) {
                throw ValidationException::withMessages(['changes' => 'После оплаты или отмены менять состав нельзя.']);
            }
            if ($data['decision'] === 'approved') {
                $items = $order->items()->get()->keyBy('id');
                $subtotal = 0;
                foreach ($change->items as $line) {
                    if (! $line['id']) {
                        $product = Product::active()->find($line['product_id']);
                        if (! $product || (int) round($product->price * 100) !== (int) round($line['price'] * 100)) {
                            throw ValidationException::withMessages(['changes' => 'Добавляемый товар недоступен или его цена изменилась. Отклоните запрос и согласуйте новый.']);
                        }
                        $minor = (int) round($line['price'] * 100) * $line['quantity'];
                        $subtotal += $minor;
                        $order->items()->create(['product_id' => $product->id, 'product_name' => $line['name'],
                            'price' => $line['price'], 'quantity' => $line['quantity'], 'total' => $minor / 100]);

                        continue;
                    }
                    $item = $items->get($line['id']);
                    if (! $item || $item->quantity !== $line['before']) {
                        throw ValidationException::withMessages(['changes' => 'Состав изменился. Отклоните запрос и согласуйте новый.']);
                    }
                    $qty = $line['quantity'];
                    if ($qty > 0 && (! $item->product || ! $item->product->is_active || ! $item->product->in_stock)) {
                        throw ValidationException::withMessages(['changes' => 'Один из товаров недоступен. Согласуйте замену или удаление.']);
                    }
                    $minor = (int) round((float) $item->price * 100) * $qty;
                    $subtotal += $minor;
                    if ($qty === 0) {
                        $item->delete();
                    } else {
                        $item->update(['quantity' => $qty, 'total' => $minor / 100]);
                    }
                }
                if ($subtotal <= 0 || $subtotal > 9000000000) {
                    throw ValidationException::withMessages(['changes' => 'Недопустимая сумма заказа.']);
                }
                $order->update(['subtotal' => $subtotal / 100, 'total' => ($subtotal + (int) round((float) $order->delivery_fee * 100)) / 100]);
            }
            $change->update(['status' => $data['decision'], 'reviewed_by' => $userId, 'reviewed_at' => now(), 'response' => $data['response'] ?? null]);
            $order->statusHistory()->create(['status' => $order->status, 'comment' => 'Сотрудник #'.$userId.($data['decision'] === 'approved' ? ': изменения приняты, сумма пересчитана.' : ': изменения отклонены.')]);
        });
    }
}
