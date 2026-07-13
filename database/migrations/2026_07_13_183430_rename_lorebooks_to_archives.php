<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('lorebooks', 'archives');

        Schema::rename('lore_entries', 'archive_entries');

        Schema::table('archive_entries', function (Blueprint $table) {
            $table->renameColumn('lorebook_id', 'archive_id');
        });
    }

    public function down(): void
    {
        Schema::table('archive_entries', function (Blueprint $table) {
            $table->renameColumn('archive_id', 'lorebook_id');
        });

        Schema::rename('archive_entries', 'lore_entries');

        Schema::rename('archives', 'lorebooks');
    }
};
