<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_submissions', function (Blueprint $table) {
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

            $table->index(['status', 'created_at']);
            $table->index(['order_id', 'created_at']);
            $table->index(['destination_id', 'transfer_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_submissions');
    }
};
