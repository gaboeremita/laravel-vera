<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('regions')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('world_sessions')->delete();

            $bridgeId = DB::table('worlds')->insertGetId([
                'name' => 'The Bridge',
                'slug' => 'the-bridge',
                'description' => '',
                'assistant_context_prompt' => '',
                'npc_context_prompt' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('regions')->update(['world_id' => $bridgeId]);
            DB::table('images')->where('imageable_type', 'App\Models\World')->update(['imageable_type' => 'App\Models\Region']);
            DB::table('tracks')->where('trackable_type', 'App\Models\World')->update(['trackable_type' => 'App\Models\Region']);

            $this->keepResidents();
            DB::table('world_residents')->update(['region_id' => DB::raw('world_id')]);
            DB::table('world_residents')->update(['world_id' => $bridgeId]);

            DB::table('world_user')->orderBy('id')->get()->groupBy('user_id')->each(function ($rows) use ($bridgeId): void {
                DB::table('world_user')->where('id', $rows->first()->id)->update(['world_id' => $bridgeId]);
                DB::table('world_user')->whereIn('id', $rows->skip(1)->pluck('id'))->delete();
            });
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Moving worlds into The Bridge cannot be reversed.');
    }

    /**
     * Yinlin stays in the Library, everyone in the Undercroft stays, and every
     * other placement is removed so each assistant is a resident of one region.
     */
    private function keepResidents(): void
    {
        $libraryId = $this->regionId('Library');
        $undercroftId = $this->regionId('Undercroft');
        $yinlinId = DB::table('assistants')->whereRaw('LOWER(name) = ?', ['yinlin'])->value('id')
            ?? throw new RuntimeException('No assistant named Yinlin was found.');

        $keptIds = DB::table('world_residents')
            ->where(fn ($query) => $query->where('world_id', $libraryId)->where('assistant_id', $yinlinId))
            ->orWhere(fn ($query) => $query->where('world_id', $undercroftId)->where('assistant_id', '!=', $yinlinId))
            ->pluck('id');

        DB::table('world_residents')->whereNotIn('id', $keptIds)->delete();
    }

    private function regionId(string $name): int
    {
        return DB::table('regions')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id')
            ?? throw new RuntimeException("No world named {$name} was found.");
    }
};
