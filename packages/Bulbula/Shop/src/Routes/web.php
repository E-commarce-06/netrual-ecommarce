<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all shop routes for the storefront.
| These routes are loaded by the ShopServiceProvider within a group which
| contains the "web" middleware group.
|
*/

Route::group([
    'middleware' => config('shop.routes.middleware', ['web']),
    'prefix' => config('shop.routes.prefix', ''),
], function () {

    /*
    |--------------------------------------------------------------------------
    | Homepage
    |--------------------------------------------------------------------------
    */
    Route::get('/', [Bulbula\Shop\Http\Controllers\HomeController::class, 'index'])->name('shop.home');

    /*
    |--------------------------------------------------------------------------
    | Product Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/products', [Bulbula\Shop\Http\Controllers\ProductController::class, 'index'])->name('shop.products.index');
    Route::get('/products/{slug}/quick-view', [Bulbula\Shop\Http\Controllers\ProductController::class, 'quickView'])->name('shop.products.quick-view');
    Route::get('/product/{slug}', [Bulbula\Shop\Http\Controllers\ProductController::class, 'show'])->name('shop.products.show');
    Route::post('/product/{slug}/quote', [Bulbula\Shop\Http\Controllers\QuoteRequestController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('shop.products.quote');

    /*
    |--------------------------------------------------------------------------
    | Category Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/category/{slug}', [Bulbula\Shop\Http\Controllers\CategoryController::class, 'show'])->name('shop.categories.show');

    /*
    |--------------------------------------------------------------------------
    | Search Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/search', [Bulbula\Shop\Http\Controllers\SearchController::class, 'index'])->name('shop.search');
    Route::get('/search/suggestions', [Bulbula\Shop\Http\Controllers\SearchController::class, 'suggestions'])->name('shop.search.suggestions');

    /*
    |--------------------------------------------------------------------------
    | Newsletter Routes
    |--------------------------------------------------------------------------
    */
    Route::post('/newsletter/subscribe', [Bulbula\Shop\Http\Controllers\NewsletterController::class, 'subscribe'])->name('shop.newsletter.subscribe');
    Route::post('/newsletter/unsubscribe', [Bulbula\Shop\Http\Controllers\NewsletterController::class, 'unsubscribe'])->name('shop.newsletter.unsubscribe');

    /*
    |--------------------------------------------------------------------------
    | Customer Account Routes
    |--------------------------------------------------------------------------
    */
    Route::group([
        'prefix' => 'account',
        'middleware' => ['auth'],
        'as' => 'shop.account.'
    ], function () {
        Route::get('/', [Bulbula\Shop\Http\Controllers\Account\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/orders', [Bulbula\Shop\Http\Controllers\Account\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [Bulbula\Shop\Http\Controllers\Account\OrderController::class, 'show'])->name('orders.show');
        Route::get('/addresses', [Bulbula\Shop\Http\Controllers\Account\AddressController::class, 'index'])->name('addresses.index');
        Route::post('/addresses', [Bulbula\Shop\Http\Controllers\Account\AddressController::class, 'store'])->name('addresses.store');
        Route::put('/addresses/{address}', [Bulbula\Shop\Http\Controllers\Account\AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [Bulbula\Shop\Http\Controllers\Account\AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::put('/addresses/{address}/default', [Bulbula\Shop\Http\Controllers\Account\AddressController::class, 'setDefault'])->name('addresses.default');
        Route::get('/wishlist', [Bulbula\Shop\Http\Controllers\Account\WishlistController::class, 'index'])->name('wishlist.index');
        Route::get('/wishlist/get', [Bulbula\Shop\Http\Controllers\Account\WishlistController::class, 'get'])->name('wishlist.get');
        Route::post('/wishlist/add', [Bulbula\Shop\Http\Controllers\Account\WishlistController::class, 'add'])->name('wishlist.add');
        Route::delete('/wishlist/item/{id}', [Bulbula\Shop\Http\Controllers\Account\WishlistController::class, 'remove'])->name('wishlist.remove');
        Route::post('/wishlist/item/{id}/move-to-cart', [Bulbula\Shop\Http\Controllers\Account\WishlistController::class, 'moveToCart'])->name('wishlist.move-to-cart');
        Route::post('/wishlist/{product}', [Bulbula\Shop\Http\Controllers\Account\WishlistController::class, 'toggle'])->name('wishlist.toggle');
        Route::delete('/wishlist/{product}', [Bulbula\Shop\Http\Controllers\Account\WishlistController::class, 'destroy'])->name('wishlist.destroy');
        Route::get('/downloads', [Bulbula\Shop\Http\Controllers\Account\DownloadController::class, 'index'])->name('downloads.index');
        Route::get('/downloads/{token}', [Bulbula\Shop\Http\Controllers\Account\DownloadController::class, 'download'])->name('downloads.file');
        Route::get('/profile', [Bulbula\Shop\Http\Controllers\Account\ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [Bulbula\Shop\Http\Controllers\Account\ProfileController::class, 'update'])->name('profile.update');
        Route::put('/password', [Bulbula\Shop\Http\Controllers\Account\ProfileController::class, 'updatePassword'])->name('password.update');
        Route::delete('/profile', [Bulbula\Shop\Http\Controllers\Account\ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Checkout Routes
    |--------------------------------------------------------------------------
    */
    Route::group([
        'prefix' => 'checkout',
        'as' => 'shop.checkout.'
    ], function () {
        Route::get('/', [Bulbula\Shop\Http\Controllers\Checkout\CheckoutController::class, 'index'])->name('index');
        Route::post('/', [Bulbula\Shop\Http\Controllers\Checkout\CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::post('/shipping', [Bulbula\Shop\Http\Controllers\Checkout\CheckoutController::class, 'updateShipping'])->middleware('throttle:20,1')->name('shipping.update');
        Route::get('/success/{order}', [Bulbula\Shop\Http\Controllers\Checkout\CheckoutController::class, 'success'])->name('success');
        Route::post('/success/{order}/manual-payment', [Bulbula\Shop\Http\Controllers\Checkout\CheckoutController::class, 'resubmitManualPayment'])
            ->middleware('throttle:5,1')
            ->name('manual-payment.resubmit');
        Route::get('/track-order', [Bulbula\Shop\Http\Controllers\Checkout\CheckoutController::class, 'guestTrack'])->name('track');
        Route::post('/track-order', [Bulbula\Shop\Http\Controllers\Checkout\CheckoutController::class, 'guestTrackLookup'])->name('track.lookup');
    });

    /*
    |--------------------------------------------------------------------------
    | Account Deletion Info (public — required by Google Play / App Store)
    |--------------------------------------------------------------------------
    */
    Route::get('/account-deletion', [Bulbula\Shop\Http\Controllers\Account\ProfileController::class, 'deletionInfo'])->name('account.deletion');
});
