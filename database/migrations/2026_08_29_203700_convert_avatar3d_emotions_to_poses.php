<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 3D avatar assistants now use poses exclusively — emotions are an
     * image-mode-only concept. Converts each avatar3d assistant's existing
     * emotions into equivalent poses (same name + blendshapes) so nothing
     * configured before this migration is lost, then removes the now-unused
     * emotion records (and any attached image/video) for those assistants.
     */
    public function up(): void
    {
        $emotions = DB::table('emotions')
            ->join('assistants', 'assistants.id', '=', 'emotions.assistant_id')
            ->where('assistants.portrait_type', 'avatar3d')
            ->select('emotions.id', 'emotions.assistant_id', 'emotions.name', 'emotions.vrm_blendshapes')
            ->get();

        if ($emotions->isEmpty()) {
            return;
        }

        foreach ($emotions as $emotion) {
            DB::table('poses')->updateOrInsert(
                ['assistant_id' => $emotion->assistant_id, 'name' => $emotion->name],
                ['vrm_blendshapes' => $emotion->vrm_blendshapes, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $emotionIds = $emotions->pluck('id');

        DB::table('images')->where('imageable_type', 'App\\Models\\Emotion')->whereIn('imageable_id', $emotionIds)->delete();
        DB::table('videos')->where('videoable_type', 'App\\Models\\Emotion')->whereIn('videoable_id', $emotionIds)->delete();
        DB::table('emotions')->whereIn('id', $emotionIds)->delete();
    }

    /**
     * Data migration only; the converted emotion records are not restorable.
     */
    public function down(): void
    {
        //
    }
};
