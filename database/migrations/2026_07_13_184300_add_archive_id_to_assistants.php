<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->foreignId('archive_id')->nullable()->constrained('archives')->nullOnDelete()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropForeign(['archive_id']);
            $table->dropColumn('archive_id');
        });
    }
};
