<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('assistants')->orderBy('id')->get()->each(function (object $assistant) {
            $prompt = is_array($assistant->prompt)
                ? $assistant->prompt
                : json_decode((string) $assistant->prompt, true);
            if (! is_array($prompt)) {
                return;
            }

            $emotionNames = DB::table('emotions')
                ->where('assistant_id', $assistant->id)
                ->orderBy('id')
                ->pluck('name')
                ->all();
            $poseNames = DB::table('poses')
                ->where('assistant_id', $assistant->id)
                ->orderBy('id')
                ->pluck('name')
                ->all();

            $names = $assistant->portrait_type === 'avatar3d' ? $poseNames : $emotionNames;
            $normalize = function (mixed $value) use (&$normalize, $names, $assistant): mixed {
                if (is_array($value)) {
                    foreach ($value as $key => $child) {
                        $value[$key] = $normalize($child);
                    }

                    return $value;
                }

                if (! is_string($value)) {
                    return $value;
                }

                $value = str_replace('[intimate]', '[intimate: true]', $value);
                foreach ($names as $name) {
                    $identifier = $assistant->portrait_type === 'avatar3d' ? 'pose' : 'emotion';
                    $value = preg_replace(
                        '/\['.preg_quote($name, '/').'\]/i',
                        '['.$identifier.': '.$name.']',
                        $value,
                    ) ?? $value;
                }

                return $value;
            };

            $prompt = $normalize($prompt);

            if ($assistant->portrait_type === 'avatar3d') {
                unset($prompt['emotion tags']);

                $poseTags = is_array($prompt['pose tags'] ?? null) ? $prompt['pose tags'] : [];
                $examplePose = $assistant->id === 10
                    ? 'thoughtful'
                    : (collect($poseNames)->first(fn (string $name) => $name !== 'default') ?? 'default');

                $prompt['pose tags'] = [
                    ...$poseTags,
                    'title' => 'Critical Rule — Pose Tags',
                    'rule' => 'Every response must include a pose tag formatted as [pose: <exact pose name>]. Use only a name from the available poses list. The tag may appear anywhere among the response control tags. Never mention the tag itself.',
                    'example' => '[pose: '.$examplePose."]\nHm. That's actually a more interesting question than you probably realize.",
                ];
            } elseif (isset($prompt['emotion tags']) && is_array($prompt['emotion tags'])) {
                $emotionTags = $prompt['emotion tags'];
                $emotionTags['title'] = 'Critical Rule — Emotion Tags';
                $emotionTags['rule'] = 'Every response must include exactly one emotion tag formatted as [emotion: <exact emotion name>]. Use only a name from the available emotions list. The tag may appear anywhere among the response control tags. Never mention the tag itself.';
                $emotionTags['example'] = '[emotion: '.($emotionNames[0] ?? 'default')."]\nYeah, I've heard that one before. Try harder.";
                $prompt['emotion tags'] = $emotionTags;
            }

            DB::table('assistants')->where('id', $assistant->id)->update([
                'prompt' => json_encode($prompt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
