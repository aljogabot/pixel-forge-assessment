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
        Schema::create('generation_recoveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('generation_id')
                ->unique()
                ->constrained();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at');
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['completed_at', 'next_attempt_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_recoveries');
    }
};
