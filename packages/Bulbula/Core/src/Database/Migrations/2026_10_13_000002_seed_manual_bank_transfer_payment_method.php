<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure existing installations receive the manual transfer method without
     * replacing any administrator-managed payment configuration.
     */
    public function up(): void
    {
        DB::table('payment_methods')->insertOrIgnore([
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
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Do not remove this row on rollback: administrators may already have
     * configured receiving accounts or orders may refer to this payment code.
     */
    public function down(): void
    {
        // Deliberately retain merchant-managed payment-method data.
    }
};
