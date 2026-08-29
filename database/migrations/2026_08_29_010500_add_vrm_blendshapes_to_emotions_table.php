<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emotions', function (Blueprint $table) {
            $table->json('vrm_blendshapes')->nullable()->after('restricted');
        });
    }

    public function down(): void
    {
        Schema::table('emotions', function (Blueprint $table) {
            $table->dropColumn('vrm_blendshapes');
        });
    }
};
