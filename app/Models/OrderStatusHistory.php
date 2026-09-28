<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    protected $fillable = ['order_id', 'status', 'comment'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
