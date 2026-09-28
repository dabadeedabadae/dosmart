<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    protected $fillable = ['name', 'city', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function drafts()
    {
        return $this->hasMany(OrderDraft::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
