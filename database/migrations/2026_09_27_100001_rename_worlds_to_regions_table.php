<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('worlds', 'regions');

        // PostgreSQL keeps the old sequence and primary key names after a rename, which would clash with the new worlds table.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER SEQUENCE worlds_id_seq RENAME TO regions_id_seq');
            DB::statement('ALTER INDEX worlds_pkey RENAME TO regions_pkey');
        }
    }

    public function down(): void
    {
        Schema::rename('regions', 'worlds');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER SEQUENCE regions_id_seq RENAME TO worlds_id_seq');
            DB::statement('ALTER INDEX regions_pkey RENAME TO worlds_pkey');
        }
    }
};
