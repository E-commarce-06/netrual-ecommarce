<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table and its indexes. Index creation is deliberately separate
     * so the migration can resume if MySQL leaves the table behind after a DDL
     * statement fails.
     */
    public function up(): void
    {
        if (! Schema::hasTable('manual_payment_submissions')) {
            Schema::create('manual_payment_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->string('payment_method_code', 100)->default('bank_transfer');
                $table->string('destination_id', 64);
                $table->json('destination_snapshot');
                $table->string('transfer_reference', 150)->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3);
                $table->string('proof_disk', 50)->default('local');
                $table->string('proof_path', 255);
                $table->string('proof_mime_type', 100);
                $table->unsignedInteger('proof_size')->nullable();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 32)->default('pending_review');
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        // Laravel's default composite-index name exceeded MySQL's 64-character
        // identifier limit for destination_id + transfer_reference. Keep these
        // names short, and detect existing indexes so a partially completed
        // MySQL migration can safely continue on the next run.
        $indexes = [
            [
                'columns' => ['status', 'created_at'],
                'name' => 'mps_status_created_at_idx',
            ],
            [
                'columns' => ['order_id', 'created_at'],
                'name' => 'mps_order_created_at_idx',
            ],
            [
                'columns' => ['destination_id', 'transfer_reference'],
                'name' => 'mps_destination_reference_idx',
            ],
        ];

        foreach ($indexes as $index) {
            if (Schema::hasIndex('manual_payment_submissions', $index['columns'])) {
                continue;
            }

            Schema::table('manual_payment_submissions', function (Blueprint $table) use ($index): void {
                $table->index($index['columns'], $index['name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_submissions');
    }
};
