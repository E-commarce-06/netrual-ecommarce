<?php

use Bulbula\Core\Models\Extension;
use Bulbula\Core\Models\PaymentMethod;
use Inertia\Testing\AssertableInertia as Assert;

test('storefront shares only active payment methods with active extensions', function () {
    PaymentMethod::query()->updateOrCreate(
        ['code' => 'cod'],
        ['name' => 'Cash on Delivery', 'type' => 'cod', 'is_active' => true, 'sort_order' => 1],
    );
    PaymentMethod::query()->updateOrCreate(
        ['code' => 'stripe'],
        ['name' => 'Stripe', 'type' => 'stripe', 'is_active' => true, 'sort_order' => 2],
    );
    PaymentMethod::query()->updateOrCreate(
        ['code' => 'paypal'],
        ['name' => 'PayPal', 'type' => 'paypal', 'is_active' => true, 'sort_order' => 3],
    );
    PaymentMethod::query()->updateOrCreate(
        ['code' => 'razorpay'],
        ['name' => 'Razorpay', 'type' => 'razorpay', 'is_active' => false, 'sort_order' => 4],
    );

    Extension::query()->updateOrCreate(
        ['code' => 'bulbula-stripe'],
        ['name' => 'Stripe', 'version' => '1.0.0', 'active' => false, 'installed' => true],
    );
    Extension::query()->updateOrCreate(
        ['code' => 'bulbula-paypal'],
        ['name' => 'PayPal', 'version' => '1.0.0', 'active' => true, 'installed' => true],
    );

    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activePaymentMethods', [
                ['code' => 'cod', 'name' => 'Cash on Delivery'],
                ['code' => 'paypal', 'name' => 'PayPal'],
            ])
        );
});
