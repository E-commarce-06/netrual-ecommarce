<?php

declare(strict_types=1);

use Bulbula\Support\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use Bulbula\Support\Http\Controllers\HelpCenterController;
use Bulbula\Support\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;

// Register before the CMS catch-all so /help always resolves to the live center.
Route::middleware('web')->group(function (): void {
    Route::get('/help', [HelpCenterController::class, 'index'])->name('help.index');

    Route::middleware('auth')->prefix('account/support')->name('shop.account.support.')->group(function (): void {
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('/create', [SupportTicketController::class, 'create'])->name('create');
        Route::post('/', [SupportTicketController::class, 'store'])->middleware('throttle:5,1')->name('store');
        Route::get('/{ticket}', [SupportTicketController::class, 'show'])->whereNumber('ticket')->name('show');
        Route::post('/{ticket}/messages', [SupportTicketController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:10,1')->name('messages.store');
    });

    Route::middleware(['auth:admin'])->prefix('admin/support')->name('admin.support.')->group(function (): void {
        Route::get('/', [AdminSupportTicketController::class, 'index'])->name('index');
        Route::get('/{ticket}', [AdminSupportTicketController::class, 'show'])->whereNumber('ticket')->name('show');
        Route::post('/{ticket}/reply', [AdminSupportTicketController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:20,1')->name('reply');
        Route::put('/{ticket}/status', [AdminSupportTicketController::class, 'update'])->whereNumber('ticket')->name('update');
    });
});
