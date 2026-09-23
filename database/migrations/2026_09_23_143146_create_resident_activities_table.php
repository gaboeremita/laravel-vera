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
        Schema::create('resident_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_resident_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('verb');
            $table->string('target')->nullable();
            $table->string('activity')->nullable();
            $table->text('reason')->nullable();
            $table->string('zone_id')->nullable();
            $table->string('outcome')->nullable();
            $table->text('outcome_reason')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['world_session_id', 'world_resident_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_activities');
    }
};
