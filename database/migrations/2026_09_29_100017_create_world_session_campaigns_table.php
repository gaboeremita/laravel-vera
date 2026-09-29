<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_session_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->json('ending')->nullable();
            $table->string('ending_status');
            $table->timestamps();

            $table->unique(['world_session_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_session_campaigns');
    }
};
