<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_session_quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_resident_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_offers');
    }
};
