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
        Schema::table('world_residents', function (Blueprint $table) {
            $table->text('opening_message')->nullable()->after('behavior_settings');
            $table->text('custom_prompt')->nullable()->after('opening_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('world_residents', function (Blueprint $table) {
            $table->dropColumn(['opening_message', 'custom_prompt']);
        });
    }
};
