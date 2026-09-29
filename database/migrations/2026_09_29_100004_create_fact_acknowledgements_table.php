<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_resident_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['world_session_id', 'fact_id', 'world_resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_acknowledgements');
    }
};
