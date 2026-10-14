<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('world_session_id')->nullable()->after('world_id')->constrained()->cascadeOnDelete();
            $table->foreignId('made_by_resident_id')->nullable()->after('world_session_id')->constrained('world_residents')->nullOnDelete();
            $table->dropUnique(['world_id', 'name']);
        });

        DB::statement('CREATE UNIQUE INDEX items_world_id_name_unique ON items (world_id, name) WHERE world_session_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX items_world_session_id_name_unique ON items (world_session_id, name) WHERE world_session_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('items')->whereNotNull('world_session_id')->delete();
        DB::statement('DROP INDEX items_world_session_id_name_unique');
        DB::statement('DROP INDEX items_world_id_name_unique');

        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('made_by_resident_id');
            $table->dropConstrainedForeignId('world_session_id');
            $table->unique(['world_id', 'name']);
        });
    }
};
