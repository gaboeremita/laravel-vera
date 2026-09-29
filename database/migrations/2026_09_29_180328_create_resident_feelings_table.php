<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_feelings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_resident_id')->constrained()->cascadeOnDelete();
            $table->float('romance')->default(0);
            $table->float('trust')->default(0);
            $table->float('liking')->default(0);
            $table->timestamps();

            $table->unique(['world_session_id', 'world_resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_feelings');
    }
};
