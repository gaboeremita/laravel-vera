<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_residents', function (Blueprint $table) {
            $table->dropForeign(['world_id']);
            $table->foreignId('region_id')->nullable()->after('world_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('world_user', function (Blueprint $table) {
            $table->dropForeign(['world_id']);
        });
    }

    public function down(): void
    {
        Schema::table('world_user', function (Blueprint $table) {
            $table->foreign('world_id')->references('id')->on('regions')->cascadeOnDelete();
        });

        Schema::table('world_residents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
            $table->foreign('world_id')->references('id')->on('regions')->cascadeOnDelete();
        });
    }
};
