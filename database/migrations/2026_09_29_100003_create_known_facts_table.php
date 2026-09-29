<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('known_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fact_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('source_name');
            $table->text('summary');
            $table->timestamp('created_at')->nullable();

            $table->unique(['world_session_id', 'fact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('known_facts');
    }
};
