<?php

namespace Bulbula\SocialAuth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAuthChallenge extends Model
{
    protected $table = 'social_auth_challenges';

    protected $fillable = [
        'type',
        'token_hash',
        'user_id',
        'device_name',
        'attempts',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
