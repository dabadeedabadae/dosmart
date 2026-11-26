<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'description', 'price', 'unit', 'image_url', 'in_stock', 'sort_order', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'in_stock' => 'boolean', 'is_active' => 'boolean'];

    public function getImageUrlAttribute(?string $value): ?string
    {
        if (! $value) return null;
        if (preg_match('~^https?://~i', $value)) return $value;
        return rtrim(config('app.url'), '/').'/'.ltrim($value, '/');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('in_stock', true);
    }
}
