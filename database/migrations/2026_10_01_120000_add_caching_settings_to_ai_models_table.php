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
        Schema::table('ai_models', function (Blueprint $table) {
            $table->boolean('cache_marks')->default(false);
            $table->string('conversation_key_field')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn(['cache_marks', 'conversation_key_field']);
        });
    }
};
