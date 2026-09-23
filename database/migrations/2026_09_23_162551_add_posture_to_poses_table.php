<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poses', function (Blueprint $table) {
            $table->string('posture')->default('standing')->after('name');
            $table->dropUnique(['assistant_id', 'name']);
            $table->unique(['assistant_id', 'name', 'posture']);
        });
    }

    public function down(): void
    {
        Schema::table('poses', function (Blueprint $table) {
            $table->dropUnique(['assistant_id', 'name', 'posture']);
            $table->unique(['assistant_id', 'name']);
            $table->dropColumn('posture');
        });
    }
};
