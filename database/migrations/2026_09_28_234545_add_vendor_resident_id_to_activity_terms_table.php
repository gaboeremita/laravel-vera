<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_terms', function (Blueprint $table) {
            $table->foreignId('vendor_resident_id')->nullable()->constrained('world_residents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_terms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_resident_id');
        });
    }
};
