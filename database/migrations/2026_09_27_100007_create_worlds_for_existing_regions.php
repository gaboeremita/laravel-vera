<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('regions')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('world_sessions')->delete();

            Schema::table('world_residents', fn (Blueprint $table) => $table->dropUnique(['world_id', 'assistant_id']));
            Schema::table('world_user', fn (Blueprint $table) => $table->dropUnique(['world_id', 'user_id']));

            $residentIds = DB::table('world_residents')->get(['id', 'world_id'])->groupBy('world_id');
            $membershipIds = DB::table('world_user')->get(['id', 'world_id'])->groupBy('world_id');

            DB::table('regions')->orderBy('id')->get()->each(function ($region) use ($residentIds, $membershipIds): void {
                $worldId = DB::table('worlds')->insertGetId([
                    'name' => $region->name,
                    'slug' => $region->slug,
                    'description' => '',
                    'assistant_context_prompt' => '',
                    'npc_context_prompt' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('regions')->where('id', $region->id)->update(['world_id' => $worldId]);
                DB::table('world_residents')
                    ->whereIn('id', $residentIds->get($region->id, collect())->pluck('id'))
                    ->update(['region_id' => $region->id, 'world_id' => $worldId]);
                DB::table('world_user')
                    ->whereIn('id', $membershipIds->get($region->id, collect())->pluck('id'))
                    ->update(['world_id' => $worldId]);
            });

            Schema::table('world_residents', fn (Blueprint $table) => $table->unique(['world_id', 'assistant_id']));
            Schema::table('world_user', fn (Blueprint $table) => $table->unique(['world_id', 'user_id']));

            DB::table('images')->where('imageable_type', 'App\Models\World')->update(['imageable_type' => 'App\Models\Region']);
            DB::table('tracks')->where('trackable_type', 'App\Models\World')->update(['trackable_type' => 'App\Models\Region']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Creating worlds for existing regions cannot be reversed.');
    }
};
