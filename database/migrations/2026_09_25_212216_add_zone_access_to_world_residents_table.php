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
        Schema::table('world_residents', function (Blueprint $table) {
            $table->json('zone_access')->nullable()->after('custom_prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('world_residents', function (Blueprint $table) {
            $table->dropColumn('zone_access');
        });
    }
};
