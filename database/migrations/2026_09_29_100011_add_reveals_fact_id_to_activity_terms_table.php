<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_terms', function (Blueprint $table) {
            $table->foreignId('reveals_fact_id')->nullable()->constrained('facts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_terms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reveals_fact_id');
        });
    }
};
