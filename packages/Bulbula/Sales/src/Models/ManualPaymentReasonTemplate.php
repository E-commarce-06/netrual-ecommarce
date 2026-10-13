<?php

namespace Bulbula\Sales\Models;

use Illuminate\Database\Eloquent\Model;

class ManualPaymentReasonTemplate extends Model
{
    protected $table = 'manual_payment_reason_templates';

    protected $fillable = [
        'code',
        'label',
        'reason',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
