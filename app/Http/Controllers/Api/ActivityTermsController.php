<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateActivityTermsRequest;
use App\Models\ActivityTerms;
use App\Models\Region;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ActivityTermsController extends Controller
{
    public function index(World $world, Region $region): JsonResponse
    {
        Gate::authorize('view', $world);

        return response()->json($region->activityTerms()->get()->map(fn (ActivityTerms $terms) => self::present($terms))->values());
    }

    public function update(UpdateActivityTermsRequest $request, World $world, Region $region, string $object, string $activity): JsonResponse
    {
        Gate::authorize('update', $world);

        $terms = $region->activityTerms()->updateOrCreate(['object_id' => $object, 'activity_id' => $activity], $request->attributesForTerms());

        return response()->json(self::present($terms));
    }

    public function destroy(World $world, Region $region, string $object, string $activity): JsonResponse
    {
        Gate::authorize('update', $world);

        $region->activityTerms()->where('object_id', $object)->where('activity_id', $activity)->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(ActivityTerms $terms): array
    {
        return [
            'objectId' => $terms->object_id,
            'activityId' => $terms->activity_id,
            'responses' => $terms->responseList(),
            'vendorResidentId' => $terms->vendor_resident_id,
        ];
    }
}
