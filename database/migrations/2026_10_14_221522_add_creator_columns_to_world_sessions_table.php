<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_sessions', function (Blueprint $table) {
            $table->timestamp('creator_mode_at')->nullable();
            $table->json('frozen_resident_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('world_sessions', function (Blueprint $table) {
            $table->dropColumn(['creator_mode_at', 'frozen_resident_ids']);
        });
    }
};
