<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Services\TelegramNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'prisoner_name'        => 'required|string|max:255',
            'squad_number'         => 'nullable|string|max:50',
            // institution_id — если фронт его прислал, свяжем заказ с реальным учреждением
            // из справочника. institution_name остаётся как отображаемый текст (для случаев,
            // когда учреждения ещё нет в справочнике), но НЕ является источником истины ни для
            // расчётов, ни для связи с Institution.
            'institution_id'       => 'nullable|integer|exists:institutions,id',
            'institution_name'     => 'required|string|max:255',
            'contact_phone'        => 'required|string|max:30',
            'relative_name'        => 'nullable|string|max:255',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.quantity'     => 'required|integer|min:1',
            'delivery_fee'         => 'required|numeric|min:0',
        ]);
        // Цена, название и суммы по позициям НЕ принимаются от клиента — они всегда
        // пересчитываются на сервере из таблицы products (см. ниже). Раньше price/total
        // по каждой позиции и subtotal/total заказа брались прямо из тела запроса и только
        // проверялись на "число >= 0", то есть клиент мог заказать что угодно по любой
        // цене, включая ноль. Это была основная брешь: контроль денег обязан быть на сервере.

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                // active() = is_active && in_stock — недоступный или снятый с продажи товар
                // купить нельзя, даже если клиент когда-то закешировал его в корзине.
                $product = Product::active()->find($item['product_id']);

                if (!$product) {
                    throw ValidationException::withMessages([
                        'items' => ["Товар с ID {$item['product_id']} недоступен или отсутствует в продаже."],
                    ]);
                }

                $price = (float) $product->price;
                $lineTotal = round($price * $item['quantity'], 2);
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $price,
                    'quantity'     => $item['quantity'],
                    'total'        => $lineTotal,
                ];
            }

            $deliveryFee = 3000;
            $total = round($subtotal + $deliveryFee, 2);

            // Сначала вставляем без номера (temporary), потом обновляем на основе ID
            $order = Order::create([
                'order_number'     => 'TMP-' . uniqid(),
                'customer_id' => $request->user('customer')?->id,
                'institution_id'   => $validated['institution_id'] ?? null,
                'prisoner_name'    => $validated['prisoner_name'],
                'squad_number'     => $validated['squad_number'] ?? null,
                'institution_name' => $validated['institution_name'],
                'contact_phone'    => $validated['contact_phone'],
                'relative_name'    => $validated['relative_name'] ?? null,
                'subtotal'         => $subtotal,
                'delivery_fee'     => $deliveryFee,
                'total'            => $total,
                'status'           => 'pending',
                'payment_status'   => 'unpaid',
            ]);

            // Номер основан на реальном ID — без race condition
            $order->update(['order_number' => Order::generateOrderNumber($order->id)]);

            foreach ($itemsData as $itemData) {
                OrderItem::create(array_merge($itemData, ['order_id' => $order->id]));
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status'   => 'pending',
                'comment'  => 'Заказ создан',
            ]);

            DB::commit();

            // Уведомляем оператора в Telegram (не блокирующее — ошибка не роллбэкает заказ)
            $order->load('items');
            app(TelegramNotifier::class)->newOrder($order);

            return response()->json([
                'order_number' => $order->order_number,
                'order_id'     => $order->id,
                'status'       => $order->status,
                'total'        => (float) $order->total,
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Order creation failed', [
                'error'   => $e->getMessage(),
                'request' => $request->except(['password']),
            ]);
            return response()->json(['error' => 'Ошибка создания заказа. Попробуйте ещё раз.'], 500);
        }
    }

    public function show(string $orderNumber)
    {
        $order = Order::with('items')->where('customer_id', request()->user('customer')->id)->where('order_number', $orderNumber)->firstOrFail();

        return response()->json([
            'order_number'     => $order->order_number,
            'status'           => $order->status,
            'status_label'     => $order->status_label,
            'payment_status'   => $order->payment_status,
            'prisoner_name'    => $order->prisoner_name,
            'institution_name' => $order->institution_name,
            'contact_phone'    => $order->contact_phone,
            'subtotal'         => (float) $order->subtotal,
            'delivery_fee'     => (float) $order->delivery_fee,
            'total'            => (float) $order->total,
            'created_at'       => $order->created_at->toISOString(),
            'items'            => $order->items->map(fn($i) => [
                'product_id'   => $i->product_id,
                'product_name' => $i->product_name,
                'price'        => (float) $i->price,
                'quantity'     => $i->quantity,
                'total'        => (float) $i->total,
            ]),
        ]);
    }

    /**
     * ВАЖНО: этот эндпоинт временно ОТКЛЮЧЁН в routes/api.php (закомментирован).
     *
     * Причина: интеграция с Kaspi Pay ещё не сделана, а этот метод в текущем виде
     * доверяет входящему запросу без какой-либо проверки подлинности — любой человек,
     * зная номер заказа, может POST'нуть сюда Status=SUCCESS и пометить заказ оплаченным
     * без реальной оплаты.
     *
     * Прежде чем снова включать роут в routes/api.php, нужно реализовать один из способов
     * проверки, которые предоставляет Kaspi Pay для вашего мерчант-аккаунта:
     *   - проверка подписи/секрета в запросе (HMAC с ключом мерчанта), либо
     *   - white-list IP адресов Kaspi, либо
     *   - (самый надёжный) обратный запрос на API Kaspi по TransactionId, чтобы
     *     самостоятельно спросить статус транзакции, не доверяя входящему POST.
     *
     * Без этого менять payment_status на 'paid' здесь нельзя.
     */
    public function kaspiWebhook(Request $request)
    {
        $transactionId = $request->input('TransactionId');
        $orderNumber   = $request->input('OrderId');
        $status        = $request->input('Status'); // SUCCESS / FAILED / CANCEL

        Log::info('Kaspi webhook received', [
            'order'  => $orderNumber,
            'status' => $status,
            'txn'    => $transactionId,
        ]);

        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            Log::warning('Kaspi webhook: order not found', ['order_number' => $orderNumber]);
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($status === 'SUCCESS') {
            $order->update([
                'status'               => 'paid',
                'payment_status'       => 'paid',
                'kaspi_transaction_id' => $transactionId,
            ]);
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status'   => 'paid',
                'comment'  => 'Оплата подтверждена через Kaspi Pay (ID: ' . $transactionId . ')',
            ]);
        } elseif (in_array($status, ['FAILED', 'CANCEL'])) {
            $order->update(['status' => 'cancelled', 'payment_status' => 'unpaid']);
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status'   => 'cancelled',
                'comment'  => 'Оплата не прошла через Kaspi Pay (статус: ' . $status . ')',
            ]);
        }

        return response()->json(['success' => true]);
    }
}
