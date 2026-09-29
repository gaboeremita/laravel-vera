<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_residents', function (Blueprint $table) {
            $table->string('public_description')->nullable()->after('custom_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('world_residents', function (Blueprint $table) {
            $table->dropColumn('public_description');
        });
    }
};
