<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worlds', function (Blueprint $table) {
            $table->string('creator_password')->nullable();
        });

        DB::table('world_user')
            ->join('users', 'users.id', '=', 'world_user.user_id')
            ->whereNotNull('users.creator_password')
            ->orderBy('world_user.id')
            ->get(['world_user.world_id', 'users.creator_password'])
            ->unique('world_id')
            ->each(fn ($row) => DB::table('worlds')->where('id', $row->world_id)->update(['creator_password' => $row->creator_password]));

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('creator_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('creator_password')->nullable();
        });

        DB::table('world_user')
            ->join('worlds', 'worlds.id', '=', 'world_user.world_id')
            ->whereNotNull('worlds.creator_password')
            ->orderBy('world_user.id')
            ->get(['world_user.user_id', 'worlds.creator_password'])
            ->unique('user_id')
            ->each(fn ($row) => DB::table('users')->where('id', $row->user_id)->update(['creator_password' => $row->creator_password]));

        Schema::table('worlds', function (Blueprint $table) {
            $table->dropColumn('creator_password');
        });
    }
};
