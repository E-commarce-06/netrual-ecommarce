<?php

use Bulbula\SocialAuth\Http\Controllers\Api\GoogleApiAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth/google')
    ->name('api.v1.auth.google.')
    ->group(function () {
        Route::get('/config', [GoogleApiAuthController::class, 'configuration'])
            ->middleware('throttle:60,1')
            ->name('configuration');

        Route::post('/nonce', [GoogleApiAuthController::class, 'issueLoginNonce'])
            ->middleware('throttle:10,1')
            ->name('nonce');

        Route::post('/login', [GoogleApiAuthController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('login');

        Route::post('/two-factor', [GoogleApiAuthController::class, 'verifyTwoFactor'])
            ->middleware('throttle:5,1')
            ->name('two-factor');

        Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
            Route::post('/link/nonce', [GoogleApiAuthController::class, 'issueLinkNonce'])
                ->name('link.nonce');

            Route::post('/link', [GoogleApiAuthController::class, 'link'])
                ->name('link');

            Route::delete('/link', [GoogleApiAuthController::class, 'unlink'])
                ->name('unlink');
        });
    });
