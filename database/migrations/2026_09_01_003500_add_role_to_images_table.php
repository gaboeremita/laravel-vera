<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes multiple images on the same imageable (e.g. a World's
     * card image vs its portrait image) — null for existing single-image
     * imageables like Assistant's card image, which stay unaffected.
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('role')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
