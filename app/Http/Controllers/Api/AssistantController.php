<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteAssistantAssets;
use App\Enums\AssistantKind;
use App\Enums\AssistantMode;
use App\Enums\AssistantPortraitType;
use App\Http\Controllers\Controller;
use App\Models\Assistant;
use App\Models\Emotion;
use App\Models\Image;
use App\Models\Pose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;

class AssistantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assistants = $request->user()
            ->assistants()
            ->where('kind', AssistantKind::Assistant)
            ->withCount(['emotions'])
            ->with(['cardImage', 'vrm'])
            ->get()
            ->map(function (Assistant $assistant) {
                $pivotId = $assistant->pivot->id;

                // Card image takes priority; the 'default' emotion image is
                // the fallback for image-mode assistants without one set.
                $cardImageUrl = $assistant->cardImage?->url;

                if (! $cardImageUrl) {
                    $defaultEmotion = $assistant->emotions()
                        ->where('name', 'default')
                        ->with('image')
                        ->first();

                    $cardImageUrl = $defaultEmotion?->image?->url;
                }

                // Conversation stats via the pivot
                $stats = DB::table('conversations')
                    ->where('assistant_user_id', $pivotId)
                    ->selectRaw('count(*) as conversations_count, max(updated_at) as last_activity')
                    ->first();

                return [
                    'id' => $assistant->id,
                    'name' => $assistant->name,
                    'slug' => $assistant->slug,
                    'description' => $assistant->description,
                    'kind' => $assistant->kind->value,
                    'portrait_type' => $assistant->portrait_type->value,
                    'vrm_url' => $assistant->vrm?->url,
                    'image_url' => $cardImageUrl,
                    'conversations_count' => (int) ($stats->conversations_count ?? 0),
                    'last_activity' => $stats->last_activity,
                ];
            });

        return response()->json($assistants);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->with(['vrm', 'cardImage'])
            ->findOrFail($id);

        $mapEmotion = fn ($emotion) => [
            'id' => $emotion->id,
            'name' => $emotion->name,
            'image_url' => $emotion->image?->url,
            'vrm_blendshapes' => $emotion->vrm_blendshapes,
        ];

        $emotions = $assistant->emotions()
            ->where('restricted', false)
            ->with('image')
            ->get()
            ->map($mapEmotion);

        $restrictedEmotions = $assistant->emotions()
            ->where('restricted', true)
            ->with('image')
            ->get()
            ->map($mapEmotion);

        $poses = $assistant->poses()
            ->with('animationFile')
            ->get()
            ->map(fn (Pose $pose) => [
                'id' => $pose->id,
                'name' => $pose->name,
                'vrm_blendshapes' => $pose->vrm_blendshapes,
                'animation_url' => $pose->animationFile?->url,
                'animation_original_name' => $pose->animationFile?->original_name,
            ]);

        return response()->json([
            'id' => $assistant->id,
            'name' => $assistant->name,
            'slug' => $assistant->slug,
            'description' => $assistant->description,
            'opening_message' => $assistant->opening_message,
            'prompt' => $assistant->prompt,
            'archive_id' => $assistant->archive_id,
            'mode' => $assistant->mode,
            'portrait_type' => $assistant->portrait_type->value,
            'vrm_url' => $assistant->vrm?->url,
            'vrm_original_name' => $assistant->vrm?->original_name,
            'image_url' => $assistant->cardImage?->url,
            'emotions' => $emotions,
            'restricted_emotions' => $restrictedEmotions,
            'poses' => $poses,
        ]);
    }

    public function store(Request $request, AssistantKind $kind = AssistantKind::Assistant): JsonResponse
    {
        if (is_string($request->input('prompt'))) {
            $request->merge(['prompt' => json_decode($request->input('prompt'), true)]);
        }

        $isAvatarMode = $request->input('portrait_type') === AssistantPortraitType::Avatar3D->value;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:assistants,slug'],
            'description' => ['nullable', 'string'],
            'opening_message' => ['nullable', 'string'],
            'prompt' => ['nullable', 'array'],
            'archive_id' => ['nullable', 'integer', 'exists:archives,id'],
            'mode' => ['sometimes', new Enum(AssistantMode::class)],
            'portrait_type' => ['sometimes', new Enum(AssistantPortraitType::class)],
            'vrm' => $kind === AssistantKind::WorldNpc
                ? ['required', 'file', 'extensions:vrm', 'max:51200']
                : ['sometimes', 'file', 'extensions:vrm', 'max:51200'],
            'emotions' => $isAvatarMode ? ['prohibited'] : ['required', 'array', 'min:1'],
            'emotions.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'emotions.*.image' => ['required', 'file', 'image', 'max:10480'],
            'restricted_emotions' => $isAvatarMode ? ['prohibited'] : ['sometimes', 'array'],
            'restricted_emotions.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'restricted_emotions.*.image' => ['required', 'file', 'image', 'max:10480'],
            'poses' => $isAvatarMode ? ['sometimes', 'array'] : ['prohibited'],
            'poses.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'poses.*.vrm_blendshapes' => ['sometimes', 'array'],
            'poses.*.vrm_blendshapes.*.expression' => ['required', 'string', 'max:100'],
            'poses.*.vrm_blendshapes.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'poses.*.animation' => ['sometimes', 'file', 'extensions:vrma,fbx', 'max:10240'],
        ]);

        $duplicateAcrossArrays = collect($validated['emotions'] ?? [])
            ->pluck('name')
            ->intersect(collect($validated['restricted_emotions'] ?? [])->pluck('name'))
            ->isNotEmpty();

        if ($duplicateAcrossArrays) {
            return response()->json([
                'message' => 'Emotion names must be unique across emotions and restricted_emotions.',
                'errors' => ['emotions' => ['Emotion names must be unique across emotions and restricted_emotions.']],
            ], 422);
        }

        if (! $isAvatarMode) {
            $hasDefault = collect($validated['emotions'] ?? [])
                ->contains(fn ($e) => $e['name'] === 'default');

            if (! $hasDefault) {
                return response()->json([
                    'message' => 'A "default" emotion is required.',
                    'errors' => ['emotions' => ['A "default" emotion is required.']],
                ], 422);
            }
        }

        $assistant = DB::transaction(function () use ($request, $validated, $kind) {
            $assistant = Assistant::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'opening_message' => $validated['opening_message'] ?? null,
                'prompt' => $validated['prompt'] ?? [],
                'archive_id' => $validated['archive_id'] ?? null,
                'mode' => $validated['mode'] ?? AssistantMode::Assistant->value,
                'portrait_type' => $validated['portrait_type'] ?? AssistantPortraitType::Image->value,
                'kind' => $kind,
            ]);

            $request->user()->assistants()->attach($assistant->id);

            if (isset($validated['vrm'])) {
                $vrm = $validated['vrm'];
                $path = $vrm->store("vrm/{$assistant->id}", 'public');

                $assistant->vrm()->create([
                    'path' => $path,
                    'disk' => 'public',
                    'mime_type' => 'application/octet-stream',
                    'size' => $vrm->getSize(),
                    'original_name' => $vrm->getClientOriginalName(),
                ]);
            }

            $storeEmotion = function (array $emotionData, bool $restricted) use ($assistant): void {
                $emotion = $assistant->emotions()->create([
                    'name' => $emotionData['name'],
                    'restricted' => $restricted,
                    'vrm_blendshapes' => Emotion::normalizeBlendshapes($emotionData['vrm_blendshapes'] ?? null),
                ]);

                if (isset($emotionData['image'])) {
                    $path = $emotionData['image']->store("emotions/{$assistant->id}", 'public');

                    $emotion->image()->create([
                        'path' => $path,
                        'disk' => 'public',
                        'mime_type' => $emotionData['image']->getMimeType(),
                        'size' => $emotionData['image']->getSize(),
                    ]);
                }
            };

            foreach ($validated['emotions'] ?? [] as $emotionData) {
                $storeEmotion($emotionData, false);
            }

            foreach ($validated['restricted_emotions'] ?? [] as $emotionData) {
                $storeEmotion($emotionData, true);
            }

            foreach ($validated['poses'] ?? [] as $poseData) {
                $pose = $assistant->poses()->create([
                    'name' => $poseData['name'],
                    'vrm_blendshapes' => Pose::normalizeBlendshapes($poseData['vrm_blendshapes'] ?? null),
                ]);

                if (isset($poseData['animation'])) {
                    // See AssistantPoseAnimationController::store for why the
                    // extension must come from the client's filename, not
                    // store()'s MIME-guessed one.
                    $filename = Str::random(40).'.'.$poseData['animation']->getClientOriginalExtension();
                    $path = $poseData['animation']->storeAs("poses/{$assistant->id}/{$pose->id}", $filename, 'public');

                    try {
                        $pose->animationFile()->create([
                            'path' => $path,
                            'disk' => 'public',
                            'mime_type' => 'application/octet-stream',
                            'size' => $poseData['animation']->getSize(),
                            'original_name' => $poseData['animation']->getClientOriginalName(),
                        ]);
                    } catch (\Throwable $e) {
                        Storage::disk('public')->delete($path);
                        throw $e;
                    }
                }
            }

            return $assistant;
        });

        return response()->json($assistant, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', "unique:assistants,slug,{$assistant->id}"],
            'description' => ['nullable', 'string'],
            'opening_message' => ['nullable', 'string'],
            'prompt' => ['nullable', 'array'],
            'archive_id' => ['nullable', 'integer', 'exists:archives,id'],
            'mode' => ['sometimes', new Enum(AssistantMode::class)],
            'portrait_type' => ['sometimes', new Enum(AssistantPortraitType::class)],
        ]);

        $assistant->update($validated);

        return response()->json($assistant);
    }

    public function destroy(Request $request, int $id, DeleteAssistantAssets $deleteAssistantAssets): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($id);
        $deleteAssistantAssets->handle($assistant);

        return response()->json(['message' => 'Assistant deleted']);
    }
}
