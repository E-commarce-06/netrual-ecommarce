<?php

use App\Models\User;
use Bulbula\Core\Models\PaymentMethod;
use Bulbula\Sales\Models\ManualPaymentSubmission;
use Bulbula\Shop\Models\Order;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function configureMobileManualTransferMethod(array $overrides = []): PaymentMethod
{
    return PaymentMethod::updateOrCreate(
        ['code' => 'bank_transfer'],
        array_merge([
            'name' => 'Manual Bank / Wallet Transfer',
            'description' => 'Transfer funds and submit a receipt.',
            'type' => 'bank_transfer',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
            'instructions' => 'Use the order number as the transfer reference.',
            'configuration' => [
                'account_destinations' => [[
                    'id' => 'mobile-wallet-1',
                    'label' => 'Business wallet',
                    'provider' => 'Telebirr',
                    'type' => 'wallet',
                    'account_name' => 'Example Store',
                    'account_number' => '0911000000',
                    'instructions' => 'Include your order number.',
                    'is_active' => true,
                ]],
                'verification_days' => 3,
                // Must never be projected into the mobile payment-method response.
                'secret_key' => 'not-for-storefront',
            ],
        ], $overrides),
    );
}

test('mobile payment methods expose safe admin instructions and active manual destinations', function () {
    $user = User::factory()->create(['role' => 'customer']);
    Sanctum::actingAs($user);
    configureMobileManualTransferMethod();

    $response = $this->getJson('/api/v1/checkout/payment-methods')->assertOk();
    $method = collect($response->json('data'))->firstWhere('code', 'bank_transfer');

    expect($method)->not->toBeNull()
        ->and($method['mobile_flow'])->toBe('manual_transfer')
        ->and($method['mobile_supported'])->toBeTrue()
        ->and($method['instructions'])->toContain('order number')
        ->and($method['manual_payment']['destinations'][0]['account_number'])->toBe('0911000000')
        ->and($method['manual_payment']['verification_days'])->toBe(3)
        ->and(array_key_exists('secret_key', $method))->toBeFalse()
        ->and(json_encode($method))->not->toContain('not-for-storefront');
});

test('mobile manual payment proof stays pending and repeat submission is idempotent', function () {
    Storage::fake('local');
    $user = User::factory()->create(['role' => 'customer']);
    Sanctum::actingAs($user);
    configureMobileManualTransferMethod();

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-MOBILE-MANUAL-1',
        'status' => Order::STATUS_PENDING,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal' => 420,
        'tax' => 0,
        'shipping_cost' => 0,
        'discount' => 0,
        'total' => 420,
        'payment_method' => 'bank_transfer',
        'customer_email' => $user->email,
    ]);

    $endpoint = "/api/v1/customer/orders/{$order->id}/manual-payment";
    $payload = [
        'destination_id' => 'mobile-wallet-1',
        'transfer_reference' => 'TXN-ET-7788',
        'payment_proof' => Illuminate\Http\UploadedFile::fake()->image('receipt.png'),
    ];

    $this->post($endpoint, $payload)
        ->assertOk()
        ->assertJsonPath('data.submission_status', ManualPaymentSubmission::STATUS_PENDING_REVIEW)
        ->assertJsonPath('data.already_submitted', false);

    $submission = ManualPaymentSubmission::query()->where('order_id', $order->id)->firstOrFail();
    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING)
        ->and($submission->status)->toBe(ManualPaymentSubmission::STATUS_PENDING_REVIEW)
        ->and(Storage::disk('local')->exists($submission->proof_path))->toBeTrue();

    $this->post($endpoint, [
        'destination_id' => 'mobile-wallet-1',
        'payment_proof' => Illuminate\Http\UploadedFile::fake()->image('duplicate.png'),
    ])
        ->assertOk()
        ->assertJsonPath('data.already_submitted', true);

    expect(ManualPaymentSubmission::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING);
});
