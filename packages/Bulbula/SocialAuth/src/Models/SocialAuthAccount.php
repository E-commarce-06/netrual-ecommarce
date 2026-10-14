<?php

namespace Bulbula\SocialAuth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAuthAccount extends Model
{
    protected $table = 'social_auth_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
    ];

    protected $hidden = [
        'provider_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
