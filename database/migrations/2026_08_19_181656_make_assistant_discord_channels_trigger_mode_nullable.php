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
        Schema::table('assistant_discord_channels', function (Blueprint $table) {
            $table->string('trigger_mode')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_discord_channels', function (Blueprint $table) {
            $table->string('trigger_mode')->nullable(false)->change();
        });
    }
};
