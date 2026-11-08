<?php

declare(strict_types=1);

namespace Bulbula\Support\Models;

use App\Models\User;
use Bulbula\Shop\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const STATUSES = ['open', 'in_progress', 'awaiting_customer', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'ticket_number', 'user_id', 'customer_name', 'customer_email', 'order_id',
        'assigned_admin_id', 'category', 'subject', 'status', 'priority',
        'last_message_at', 'resolved_at', 'closed_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('created_at');
    }
}
