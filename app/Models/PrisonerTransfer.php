<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrisonerTransfer extends Model
{
    protected $fillable = ['application_number', 'application_date', 'amount_minor', 'registered_by', 'source', 'sender_name'];

    protected $casts = ['application_date' => 'date', 'amount_minor' => 'integer', 'confirmed_at' => 'datetime', 'closed_at' => 'datetime'];

    public function prisoner()
    {
        return $this->belongsTo(Prisoner::class);
    }
}
