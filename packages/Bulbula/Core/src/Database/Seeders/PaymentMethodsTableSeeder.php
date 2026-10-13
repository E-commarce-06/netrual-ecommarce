<?php

namespace Bulbula\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            // Cash on Delivery
            [
                'code' => 'cod',
                'name' => 'Cash on Delivery',
                'description' => 'Pay with cash when your order is delivered',
                'type' => 'cod',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
                'instructions' => 'Please keep exact change ready. Our delivery person will collect payment upon delivery.',
                'configuration' => json_encode([
                    'min_order_amount' => 0,
                    'max_order_amount' => null,
                    'handling_fee' => 0,
                    'handling_fee_type' => 'flat',
                    'allowed_countries' => ['*'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Manual transfer is available in settings, but stays inactive
            // until an administrator adds at least one receiving account.
            [
                'code' => 'bank_transfer',
                'name' => 'Manual Bank / Wallet Transfer',
                'description' => 'Pay by bank transfer or mobile wallet. Staff verify the transfer before confirming payment.',
                'type' => 'bank_transfer',
                'is_active' => false,
                'is_default' => false,
                'sort_order' => 2,
                'instructions' => 'Transfer the exact order total to an active receiving account and upload your receipt for staff verification.',
                'configuration' => json_encode([
                    'account_destinations' => [],
                    'min_order_amount' => 0,
                    'max_order_amount' => null,
                    'verification_days' => 3,
                    'allowed_countries' => ['*'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Stripe
            [
                'code' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Pay securely using Stripe with credit card, Apple Pay, Google Pay, and more',
                'type' => 'stripe',
                'is_active' => false,
                'is_default' => false,
                'sort_order' => 3,
                'instructions' => 'You will be redirected to Stripe to complete your payment securely.',
                'configuration' => json_encode([
                    'public_key' => env('STRIPE_PUBLIC_KEY', ''),
                    'enable_3d_secure' => true,
                    'save_payment_method' => true,
                    'payment_methods' => [
                        'card' => true,
                        'apple_pay' => true,
                        'google_pay' => true,
                        'ideal' => false,
                        'bancontact' => false,
                        'eps' => false,
                        'giropay' => false,
                        'klarna' => false,
                        'p24' => false,
                        'alipay' => false,
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($paymentMethods as $method) {
            if ($method['code'] === 'bank_transfer') {
                // This method has administrator-managed account details and
                // instructions. Only create its defaults when it is missing.
                DB::table('payment_methods')->insertOrIgnore($method);
                continue;
            }

            DB::table('payment_methods')->updateOrInsert(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
