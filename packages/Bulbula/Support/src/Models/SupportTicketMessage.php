<?php

declare(strict_types=1);

namespace Bulbula\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'author_type', 'author_id', 'author_name', 'body', 'is_internal'];

    protected $casts = ['is_internal' => 'boolean'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
