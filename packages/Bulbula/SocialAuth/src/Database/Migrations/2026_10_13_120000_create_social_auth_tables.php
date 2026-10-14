<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_auth_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('active_environment', 16)->default('test');
            $table->boolean('web_enabled')->default(false);
            $table->boolean('api_enabled')->default(false);
            $table->boolean('registration_enabled')->default(true);
            $table->unsignedTinyInteger('max_id_token_age_minutes')->default(10);
            $table->string('test_client_id')->nullable();
            $table->text('test_client_secret')->nullable();
            $table->json('test_api_audiences')->nullable();
            $table->string('live_client_id')->nullable();
            $table->text('live_client_secret')->nullable();
            $table->json('live_api_audiences')->nullable();
            $table->timestamps();
        });

        Schema::create('social_auth_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_user_id', 191);
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id'], 'social_auth_provider_subject_unique');
            $table->unique(['user_id', 'provider'], 'social_auth_user_provider_unique');
        });

        Schema::create('social_auth_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('device_name')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['type', 'expires_at']);
            $table->index(['user_id', 'type', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_auth_challenges');
        Schema::dropIfExists('social_auth_accounts');
        Schema::dropIfExists('social_auth_configurations');
    }
};
