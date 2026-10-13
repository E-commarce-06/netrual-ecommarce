<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('manual payment submissions migration resumes when a compound index is missing', function () {
    Schema::table('manual_payment_submissions', function (Blueprint $table): void {
        $table->dropIndex('mps_destination_reference_idx');
    });

    expect(Schema::hasIndex('manual_payment_submissions', ['destination_id', 'transfer_reference']))
        ->toBeFalse();

    $migration = require base_path(
        'packages/Bulbula/Sales/src/Database/Migrations/2026_10_13_000001_create_manual_payment_submissions_table.php'
    );
    $migration->up();

    expect(Schema::hasIndex('manual_payment_submissions', ['status', 'created_at']))
        ->toBeTrue()
        ->and(Schema::hasIndex('manual_payment_submissions', ['order_id', 'created_at']))
        ->toBeTrue()
        ->and(Schema::hasIndex('manual_payment_submissions', ['destination_id', 'transfer_reference']))
        ->toBeTrue();
});
