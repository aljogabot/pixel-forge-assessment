<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained();

            $table->uuid('idempotency_key')->unique();
            $table->string('provider_generation_id')->nullable()->unique();

            $table->text('prompt');
            $table->string('size', 20);
            $table->string('quality', 10);
            $table->json('reference_images')->nullable();

            $table->string('status', 20)->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();

            $table->decimal('estimated_charge_usd', 20, 12);
            $table->decimal('provider_cost_usd', 20, 12)->nullable();
            $table->decimal('actual_charge_usd', 20, 12)->nullable();

            $table->json('usage')->nullable();
            $table->json('rates')->nullable();

            $table->index(['user_id', 'id']);
            $table->index(['status', 'created_at']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
