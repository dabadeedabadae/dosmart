<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDraft;
use Illuminate\Support\Facades\DB;

class TerminalSubmission
{
    public function submit(array $data, string $subject): Order
    {
        $payload = array_intersect_key($data, array_flip(['items', 'prisoner_name', 'institution_name', 'contact_phone']));
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $service = app(PilotCheckout::class);

        return DB::transaction(function () use ($data, $subject, $hash, $service) {
            $draft = OrderDraft::where('request_id', $data['request_id'])->lockForUpdate()->first();
            if (! $draft) {
                $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
                $code = '';
                for ($i = 0; $i < 8; $i++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $draft = OrderDraft::createOrFirst(['request_id' => $data['request_id']], [
                    'code' => $code, 'source' => 'terminal', 'terminal_subject' => $subject,
                    'submission_hash' => $hash, 'items' => $data['items'],
                    'prisoner_name' => $data['prisoner_name'], 'expires_at' => now()->addDays(config('pilot.draft_days')),
                ]);
                $draft = OrderDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            }
            abort_unless($draft->terminal_subject === $subject && $draft->submission_hash === $hash, 409, 'Эта попытка уже связана с другой заявкой.');
            if ($draft->order_id) {
                return $draft->order;
            }
            $quote = $service->quote($data['items']);
            $order = Order::create([
                'order_number' => 'DOS-'.$draft->code,
                'prisoner_name' => $data['prisoner_name'], 'institution_name' => $data['institution_name'],
                'contact_phone' => $data['contact_phone'], 'subtotal' => $quote['subtotal'],
                'delivery_fee' => 0, 'total' => $quote['subtotal'], 'delivery_type' => 'unconfirmed',
                'status' => 'new', 'payment_status' => 'unpaid',
                'consented_at' => now(), 'consent_version' => config('pilot.consent_version'),
            ]);
            $order->items()->createMany($quote['items']);
            $order->statusHistory()->create(['status' => 'new', 'comment' => 'Заявка с терминала. Данные введены пользователем; сотруднику нужно связаться с родственником и согласовать доставку.']);
            $draft->update(['order_id' => $order->id]);
            $service->record($draft, 'submission_received');

            return $order;
        });
    }
}
