<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Снимок месячного лимита: нужен, чтобы перенос остатка считался по лимиту того месяца. */
class PrisonerLimitPeriod extends Model
{
    protected $fillable = ['period', 'limit_minor'];

    protected $casts = ['limit_minor' => 'integer'];
}
