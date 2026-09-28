<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'customer_id', 'user_id', 'institution_id',
        'prisoner_name', 'squad_number', 'institution_name', 'contact_phone', 'relative_name',
        'subtotal', 'delivery_fee', 'total',
        'status', 'payment_status', 'kaspi_transaction_id',
        'notes', 'delivered_at', 'delivery_type', 'consented_at', 'consent_version', 'paid_at', 'refund_amount',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'consented_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    // Номер генерируется на основе ID уже после вставки — никакого race condition
    public static function generateOrderNumber(int $id): string
    {
        return 'DOS-'.str_pad($id, 5, '0', STR_PAD_LEFT);
    }

    public function draft()
    {
        return $this->hasOne(OrderDraft::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'new' => 'Новая заявка',
            'refunded' => 'Возврат',
            'pending' => 'Ожидает оплаты',
            'paid' => 'Оплачен',
            'checking' => 'На проверке',
            'processing' => 'Собирается',
            'delivering' => 'В доставке',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменён',
            default => $this->status,
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Оплачено',
            'refunded' => 'Возврат',
            default => 'Ожидает оплаты',
        };
    }
}
