<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_session_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('object_id');
            $table->json('state');
            $table->timestamps();

            $table->unique(['world_session_id', 'region_id', 'object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_session_objects');
    }
};
