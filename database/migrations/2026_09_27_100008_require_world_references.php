<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->foreignId('world_id')->nullable(false)->change();
        });

        Schema::table('world_user', function (Blueprint $table) {
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
        });

        Schema::table('world_residents', function (Blueprint $table) {
            $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
            $table->foreignId('region_id')->nullable(false)->change();
        });

        Schema::table('world_session_residents', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('world_session_residents', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->change();
        });

        Schema::table('world_residents', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->change();
            $table->dropForeign(['world_id']);
        });

        Schema::table('world_user', function (Blueprint $table) {
            $table->dropForeign(['world_id']);
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->foreignId('world_id')->nullable()->change();
        });
    }
};
