<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_relays', function (Blueprint $table) {
            $table->foreignId('fact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_resident_id')->constrained()->cascadeOnDelete();

            $table->primary(['fact_id', 'world_resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_relays');
    }
};
