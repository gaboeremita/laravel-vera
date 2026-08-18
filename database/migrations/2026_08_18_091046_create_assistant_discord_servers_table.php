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
        Schema::create('assistant_discord_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_user_id')->constrained('assistant_user')->cascadeOnDelete();
            $table->foreignId('discord_server_id')->constrained()->cascadeOnDelete();
            $table->text('prompt')->nullable();
            $table->unique(['assistant_user_id', 'discord_server_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_discord_servers');
    }
};
