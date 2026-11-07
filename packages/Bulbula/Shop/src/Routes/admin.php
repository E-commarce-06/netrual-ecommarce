<?php

declare(strict_types=1);

use Bulbula\Shop\Http\Controllers\Admin\HomepageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:admin'])
    ->prefix('admin/content')
    ->name('admin.content.')
    ->group(function () {
        Route::get('homepage', [HomepageController::class, 'index'])->name('homepage.index');
        Route::post('homepage', [HomepageController::class, 'save'])->name('homepage.save');
    });
