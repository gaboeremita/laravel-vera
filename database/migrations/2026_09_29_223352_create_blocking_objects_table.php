<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocking_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('object_id');
            $table->timestamps();

            $table->unique(['region_id', 'object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocking_objects');
    }
};
