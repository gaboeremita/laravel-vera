<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copies each world's existing user_id into the new world_user pivot so
     * ownership survives the move to a shared, Assistant-style relationship.
     */
    public function up(): void
    {
        $worlds = DB::table('worlds')->select('id', 'user_id')->get();

        foreach ($worlds as $world) {
            DB::table('world_user')->updateOrInsert(
                ['world_id' => $world->id, 'user_id' => $world->user_id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Data migration only; nothing structural to reverse.
     */
    public function down(): void
    {
        //
    }
};
