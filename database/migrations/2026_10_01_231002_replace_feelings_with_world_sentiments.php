<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worlds', function (Blueprint $table) {
            $table->json('sentiments')->nullable();
        });

        Schema::rename('resident_feelings', 'resident_sentiments');

        Schema::table('resident_sentiments', function (Blueprint $table) {
            $table->dropColumn(['romance', 'trust', 'liking']);
            $table->json('values')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('resident_sentiments', function (Blueprint $table) {
            $table->dropColumn('values');
            $table->float('romance')->default(0);
            $table->float('trust')->default(0);
            $table->float('liking')->default(0);
        });

        Schema::rename('resident_sentiments', 'resident_feelings');

        Schema::table('worlds', function (Blueprint $table) {
            $table->dropColumn('sentiments');
        });
    }
};
