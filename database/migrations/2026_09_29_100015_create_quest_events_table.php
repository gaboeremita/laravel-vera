<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_quest_id')->constrained()->cascadeOnDelete();
            $table->string('beat', 80)->nullable();
            $table->string('type');
            $table->json('payload');
            $table->boolean('by_creator')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_events');
    }
};
