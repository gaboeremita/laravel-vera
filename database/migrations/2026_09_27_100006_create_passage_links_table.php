<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passage_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('passage_id');
            $table->foreignId('target_region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('target_passage_id');
            $table->timestamps();

            $table->unique(['region_id', 'passage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passage_links');
    }
};
