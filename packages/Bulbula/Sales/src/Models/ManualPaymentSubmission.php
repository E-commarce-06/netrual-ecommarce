<?php

namespace Bulbula\Sales\Models;

use App\Models\User;
use Bulbula\Core\Models\PaymentMethod;
use Bulbula\Shop\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPaymentSubmission extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'payment_method_id',
        'payment_method_code',
        'destination_id',
        'destination_snapshot',
        'transfer_reference',
        'amount',
        'currency',
        'proof_disk',
        'proof_path',
        'proof_mime_type',
        'proof_size',
        'submitted_by_user_id',
        'status',
        'reviewed_by_user_id',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'destination_snapshot' => 'array',
        'amount' => 'decimal:2',
        'proof_size' => 'integer',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
