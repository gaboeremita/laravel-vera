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
        Schema::ensureVectorExtensionExists();

        Schema::table('archive_entries', function (Blueprint $table) {
            $table->fullText(['title', 'content'])->language('english');
            $table->vector('embedding', dimensions: 768)->nullable()->index()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_entries', function (Blueprint $table) {
            $table->dropFullText(['title', 'content']);
            $table->dropIndex(['embedding']);
        });
    }
};
