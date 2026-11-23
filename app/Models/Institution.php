<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    protected $fillable = ['name', 'order_code', 'city', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'parcel_limits' => 'array',
        'parcel_limits_mrp' => 'array', 'parcel_mrp_rates' => 'array', 'spending_limits_mrp' => 'array'];

    /** Код, который сотрудник вводит на планшете, чтобы привязать его к учреждению. */
    public function issueTabletCode(): string
    {
        $code = (string) random_int(10000000, 99999999);
        $this->forceFill(['tablet_code' => $code])->save();

        return $code;
    }

    public function tabletSignature(): ?string
    {
        return $this->tablet_code === null ? null
            : substr(hash_hmac('sha256', 'tablet|'.$this->id.'|'.$this->tablet_code, (string) config('app.key')), 0, 24);
    }

    public function requiresParcelCode(): bool
    {
        return $this->parcel_limits !== null || $this->parcel_limits_mrp !== null;
    }

    public function drafts()
    {
        return $this->hasMany(OrderDraft::class);
    }

    public function prisoners()
    {
        return $this->hasMany(Prisoner::class);
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
