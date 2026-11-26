<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Просьба родственника пополнить счёт осуждённого; деньги зачисляет сотрудник после сверки. */
class DepositRequest extends Model
{
    public const STATUSES = ['new' => 'Ждёт оплаты', 'credited' => 'Зачислено', 'rejected' => 'Отклонено'];

    protected $fillable = ['sender_name', 'sender_phone', 'amount_minor'];

    protected $casts = ['amount_minor' => 'integer', 'handled_at' => 'datetime'];

    public function number(): string
    {
        return 'Р-'.$this->id;
    }

    public function prisoner()
    {
        return $this->belongsTo(Prisoner::class);
    }

    public function transfer()
    {
        return $this->belongsTo(PrisonerTransfer::class);
    }
}
