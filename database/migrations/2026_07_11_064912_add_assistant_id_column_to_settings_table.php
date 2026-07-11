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
        Schema::table('settings', function (Blueprint $table) {
			$table->foreignId('assistant_id')->nullable()->constrained()->cascadeOnDelete();
			$table->unique(['user_id', 'assistant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
			$table->dropUnique(['user_id', 'assistant_id']);
			$table->dropConstrainedForeignId('assistant_id');
        });
    }
};
