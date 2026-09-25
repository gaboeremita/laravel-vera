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
        Schema::table('messages', function (Blueprint $table) {
            $table->json('expression')->nullable();
        });

        DB::table('messages')->whereNotNull('emotion')->orderBy('id')->chunkById(500, function ($messages): void {
            foreach ($messages as $message) {
                DB::table('messages')->where('id', $message->id)->update(['expression' => json_encode(['emotion' => $message->emotion])]);
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('emotion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('emotion')->nullable();
        });

        DB::table('messages')->whereNotNull('expression')->orderBy('id')->chunkById(500, function ($messages): void {
            foreach ($messages as $message) {
                $emotion = json_decode($message->expression, true)['emotion'] ?? null;
                if ($emotion !== null) {
                    DB::table('messages')->where('id', $message->id)->update(['emotion' => $emotion]);
                }
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('expression');
        });
    }
};
