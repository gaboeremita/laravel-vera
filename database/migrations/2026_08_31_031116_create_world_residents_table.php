<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->json('position');
            $table->json('rotation')->nullable();
            $table->string('behavior')->default('stationary');
            $table->json('behavior_settings')->nullable();
            $table->timestamps();

            $table->unique(['world_id', 'assistant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_residents');
    }
};
