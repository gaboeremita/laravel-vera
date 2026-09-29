<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_resident_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 120);
            $table->text('content');
            $table->text('disclosure');
            $table->timestamps();

            $table->unique(['world_resident_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facts');
    }
};
