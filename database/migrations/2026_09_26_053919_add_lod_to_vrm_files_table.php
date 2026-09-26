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
        Schema::table('vrm_files', function (Blueprint $table) {
            $table->string('lod_path')->nullable()->after('path');
            $table->unsignedBigInteger('lod_size')->nullable()->after('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vrm_files', function (Blueprint $table) {
            $table->dropColumn(['lod_path', 'lod_size']);
        });
    }
};
