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
        Schema::create('world_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_user_id')->constrained('world_user')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->json('position')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('world_sessions');
    }
};
