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
        Schema::create('image_gen_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('image_gen_providers')->cascadeOnDelete();
            $table->string('name');
            $table->string('endpoint');
            $table->text('prompt')->nullable();
            $table->json('config')->nullable();
            $table->json('additional_config')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_gen_models');
    }
};
