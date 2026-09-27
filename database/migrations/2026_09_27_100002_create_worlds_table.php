<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worlds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description');
            $table->text('assistant_context_prompt');
            $table->text('npc_context_prompt');
            $table->foreignId('spawn_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('spawn_passage_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worlds');
    }
};
