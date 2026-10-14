<?php

use Bulbula\SocialAuth\Http\Controllers\Account\GoogleAccountConnectionController;
use Bulbula\SocialAuth\Http\Controllers\Auth\GoogleWebAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/auth/google/redirect', [GoogleWebAuthController::class, 'redirect'])
        ->middleware(['guest:web', 'throttle:10,1'])
        ->name('social-auth.google.redirect');

    Route::get('/auth/google/callback', [GoogleWebAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('social-auth.google.callback');

    Route::middleware('auth:web')
        ->prefix('account/security')
        ->name('shop.account.security.')
        ->group(function () {
            Route::get('/social', [GoogleAccountConnectionController::class, 'index'])
                ->name('social.index');

            Route::post('/google/connect', [GoogleAccountConnectionController::class, 'connect'])
                ->middleware('throttle:5,1')
                ->name('google.connect');

            Route::delete('/google', [GoogleAccountConnectionController::class, 'disconnect'])
                ->middleware('throttle:5,1')
                ->name('google.disconnect');
        });
});
