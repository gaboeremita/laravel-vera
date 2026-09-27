<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_sessions', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('world_user_id')->constrained()->nullOnDelete();
        });

        Schema::table('world_session_residents', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('world_resident_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('world_session_residents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
        });

        Schema::table('world_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
        });
    }
};
