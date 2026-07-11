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
		Schema::table('conversations', function (Blueprint $table) {
			$table->dropConstrainedForeignId('user_id');
			$table->foreignId('assistant_user_id')->nullable()->constrained('assistant_user')->cascadeOnDelete();
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
		Schema::table('conversations', function (Blueprint $table) {
			$table->dropConstrainedForeignId('assistant_user_id');
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
		});
    }
};
