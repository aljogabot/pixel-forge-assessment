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
        Schema::table('generation_webhook_events', function (Blueprint $table) {
            //
            $table->foreignId('generation_id')
                ->nullable()
                ->constrained('generations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generation_webhook_events', function (Blueprint $table) {
            //
            $table->dropConstrainedForeignId('generation_id');
        });
    }
};
