<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_session_quests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('run');
            $table->string('status');
            $table->json('state');
            $table->json('ending')->nullable();
            $table->string('ending_status')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['world_session_id', 'quest_id', 'run']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_session_quests');
    }
};
