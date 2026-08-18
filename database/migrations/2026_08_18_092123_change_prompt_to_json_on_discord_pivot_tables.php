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
        Schema::table('assistant_discord_servers', function (Blueprint $table) {
            $table->dropColumn('prompt');
        });

        Schema::table('assistant_discord_servers', function (Blueprint $table) {
            $table->json('prompt')->nullable();
        });

        Schema::table('assistant_discord_channels', function (Blueprint $table) {
            $table->dropColumn('prompt');
        });

        Schema::table('assistant_discord_channels', function (Blueprint $table) {
            $table->json('prompt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_discord_servers', function (Blueprint $table) {
            $table->dropColumn('prompt');
        });

        Schema::table('assistant_discord_servers', function (Blueprint $table) {
            $table->text('prompt')->nullable();
        });

        Schema::table('assistant_discord_channels', function (Blueprint $table) {
            $table->dropColumn('prompt');
        });

        Schema::table('assistant_discord_channels', function (Blueprint $table) {
            $table->text('prompt')->nullable();
        });
    }
};
