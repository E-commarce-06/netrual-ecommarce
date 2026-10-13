<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('manual_payment_reason_templates')) {
            Schema::create('manual_payment_reason_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64);
                $table->string('label', 120);
                $table->text('reason');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('manual_payment_reason_templates', ['code'])) {
            Schema::table('manual_payment_reason_templates', function (Blueprint $table): void {
                $table->unique('code', 'mprt_code_unique');
            });
        }

        if (! Schema::hasIndex('manual_payment_reason_templates', ['is_active', 'sort_order'])) {
            Schema::table('manual_payment_reason_templates', function (Blueprint $table): void {
                $table->index(['is_active', 'sort_order'], 'mprt_active_sort_idx');
            });
        }

        $now = now();
        $reasons = [
            [
                'code' => 'receipt_unreadable',
                'label' => 'Receipt is unreadable',
                'reason' => 'We could not read the receipt clearly. Please upload a sharper screenshot showing the amount, recipient, transaction date, and transfer status.',
                'sort_order' => 10,
            ],
            [
                'code' => 'transfer_not_found',
                'label' => 'Transfer not found yet',
                'reason' => 'We could not find this transfer in the receiving account yet. Please confirm with your bank or wallet that it has completed, then send an updated receipt.',
                'sort_order' => 20,
            ],
            [
                'code' => 'amount_mismatch',
                'label' => 'Amount does not match',
                'reason' => 'The amount shown on the receipt does not match the order total. Please check the amount and contact us if you believe this is incorrect.',
                'sort_order' => 30,
            ],
            [
                'code' => 'reference_missing',
                'label' => 'Reference is missing or unclear',
                'reason' => 'The receipt does not show a clear transfer reference. Please provide an updated receipt with the transaction reference visible.',
                'sort_order' => 40,
            ],
            [
                'code' => 'wrong_recipient',
                'label' => 'Recipient does not match',
                'reason' => 'The recipient shown on the receipt does not match the receiving account for this order. Please contact us before making another transfer.',
                'sort_order' => 50,
            ],
            [
                'code' => 'receipt_wrong_order',
                'label' => 'Receipt appears to be for another order',
                'reason' => 'This receipt appears to be for a different order or an earlier submission. Please send the receipt for this order.',
                'sort_order' => 60,
            ],
        ];

        foreach ($reasons as $reason) {
            DB::table('manual_payment_reason_templates')->insertOrIgnore([
                ...$reason,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_reason_templates');
    }
};
