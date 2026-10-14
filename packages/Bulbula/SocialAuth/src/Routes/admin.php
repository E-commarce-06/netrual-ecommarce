<?php

use Bulbula\SocialAuth\Http\Controllers\Admin\GoogleAuthenticationSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:admin', 'prevent.user.admin'])
    ->prefix('admin/system/authentication/google')
    ->name('admin.system.authentication.google.')
    ->group(function () {
        Route::get('/', [GoogleAuthenticationSettingsController::class, 'index'])
            ->name('index');

        Route::put('/', [GoogleAuthenticationSettingsController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('update');
    });
