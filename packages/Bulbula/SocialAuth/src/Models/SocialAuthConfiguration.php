<?php

namespace Bulbula\SocialAuth\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAuthConfiguration extends Model
{
    protected $table = 'social_auth_configurations';

    protected $fillable = [
        'provider',
        'enabled',
        'active_environment',
        'web_enabled',
        'api_enabled',
        'registration_enabled',
        'max_id_token_age_minutes',
        'test_client_id',
        'test_client_secret',
        'test_api_audiences',
        'live_client_id',
        'live_client_secret',
        'live_api_audiences',
    ];

    protected $hidden = [
        'test_client_secret',
        'live_client_secret',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'web_enabled' => 'boolean',
            'api_enabled' => 'boolean',
            'registration_enabled' => 'boolean',
            'max_id_token_age_minutes' => 'integer',
            'test_client_secret' => 'encrypted',
            'live_client_secret' => 'encrypted',
            'test_api_audiences' => 'array',
            'live_api_audiences' => 'array',
        ];
    }
}
