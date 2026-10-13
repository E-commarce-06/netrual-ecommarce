<?php

use App\Models\User;
use Bulbula\Core\Database\Seeders\PaymentMethodsTableSeeder;
use Bulbula\Core\Models\PaymentMethod;
use Inertia\Testing\AssertableInertia as Assert;

test('manual transfer is listed and its instructions and multiple receiving accounts are configurable', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    $method = PaymentMethod::query()
        ->where('code', 'bank_transfer')
        ->firstOrFail();

    expect($method->is_active)->toBeFalse();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.settings.payment-methods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/PaymentMethods/Index')
            ->where('paymentMethods.0.code', 'bank_transfer')
        );

    $configureUrl = route('admin.settings.payment-methods.configure', [
        'type' => 'bank_transfer',
    ]);

    $this->get($configureUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/PaymentMethods/ConfigureBankTransfer')
            ->where('method.code', 'bank_transfer')
        );

    $destinations = [
        [
            'id' => 'cbe-main',
            'label' => 'CBE — Main account',
            'provider' => 'Commercial Bank of Ethiopia',
            'type' => 'bank',
            'account_name' => 'Example Trading PLC',
            'account_number' => '1000000000',
            'instructions' => 'Use the order number as the transfer reference.',
            'is_active' => true,
        ],
        [
            'id' => 'telebirr-business',
            'label' => 'Telebirr business wallet',
            'provider' => 'Telebirr',
            'type' => 'wallet',
            'account_name' => 'Example Trading PLC',
            'account_number' => '0911000000',
            'instructions' => '',
            'is_active' => true,
        ],
    ];

    $instructions = 'Transfer the exact order total and upload the receipt for verification.';

    $this->from($configureUrl)
        ->post(route('admin.settings.payment-methods.save', [
            'type' => 'bank_transfer',
        ]), [
            'name' => 'Manual Bank / Wallet Transfer',
            'description' => 'Choose a business bank account or mobile wallet.',
            'instructions' => $instructions,
            'configuration' => [
                'account_destinations' => $destinations,
                'min_order_amount' => 0,
                'max_order_amount' => null,
                'verification_days' => 3,
                'allowed_countries' => ['*'],
            ],
        ])
        ->assertRedirect($configureUrl)
        ->assertSessionHasNoErrors();

    $method->refresh();

    expect($method->description)->toBe('Choose a business bank account or mobile wallet.')
        ->and($method->instructions)->toBe($instructions)
        ->and($method->configuration['account_destinations'])->toBe($destinations);

    // Running the installer seeder again must not erase merchant-owned details.
    $this->seed(PaymentMethodsTableSeeder::class);
    $method->refresh();

    expect($method->description)->toBe('Choose a business bank account or mobile wallet.')
        ->and($method->instructions)->toBe($instructions)
        ->and($method->configuration['account_destinations'])->toBe($destinations);
});
