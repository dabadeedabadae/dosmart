<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'name_kk', 'name_en', 'icon', 'color', 'parent_id', 'sort_order', 'is_active'];

    public function descendantIds(): array
    {
        $rows = self::all(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$this->id];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($rows->get($ids[$i], collect()) as $child) {
                if (! in_array($child->id, $ids, true)) {
                    $ids[] = $child->id;
                }
            }
        }

        return $ids;
    }

    public function getPathNameAttribute(): string
    {
        $names = [$this->name];
        $seen = [$this->id];
        $parent = $this->parent;
        while ($parent && ! in_array($parent->id, $seen, true)) {
            array_unshift($names, $parent->name);
            $seen[] = $parent->id;
            $parent = $parent->parent;
        }

        return implode(' / ', $names);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
