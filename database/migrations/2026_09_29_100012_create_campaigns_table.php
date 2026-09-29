<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('title', 120);
            $table->json('definition');
            $table->timestamps();

            $table->unique(['world_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
