<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_session_residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_resident_id')->constrained()->cascadeOnDelete();
            $table->json('position');
            $table->json('rotation')->nullable();
            $table->string('spot_id')->nullable();
            $table->string('activity_id')->nullable();
            $table->string('posture')->default('standing');
            $table->json('exit_position')->nullable();
            $table->timestamps();

            $table->unique(['world_session_id', 'world_resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_session_residents');
    }
};
