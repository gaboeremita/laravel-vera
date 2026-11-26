<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('state_entry_id')->nullable()->constrained('archive_entries')->nullOnDelete();
            $table->foreignId('offered_by_assistant_id')->nullable()->constrained('assistants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offered_by_assistant_id');
            $table->dropConstrainedForeignId('state_entry_id');
        });
    }
};
