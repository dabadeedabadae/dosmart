<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderChangeRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['items' => 'array', 'reviewed_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
