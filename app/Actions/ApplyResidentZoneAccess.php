<?php

namespace App\Actions;

use App\Models\Region;
use App\Models\WorldResident;

class ApplyResidentZoneAccess
{
    public const OPEN = 'open';

    public const ALLOWED = 'allowed';

    public const PRIVATE = 'private';

    public const OUTSIDE_AREA = 'outsideArea';

    /**
     * The world as one resident knows it: secret zones they have no access to
     * are gone along with everything inside them, and every other zone says
     * whether they may enter it on their own. A resident who keeps to an area
     * in their home region knows every zone there too, and the ones outside
     * it are marked as places they go only when the user asks; in any other
     * region the area does not apply. The returned world is an unsaved copy
     * and must never be saved.
     */
    public function handle(Region $region, ?WorldResident $resident): Region
    {
        $layout = $region->layout ?? [];
        if ($resident === null || empty($layout['zones'])) {
            return $region;
        }

        $zonesById = collect($layout['zones'])->keyBy('id')->all();
        $area = $resident->region_id === $region->id ? $resident->areaZoneIds() : [];
        $zones = [];
        $hiddenZoneIds = [];
        foreach ($layout['zones'] as $zone) {
            $access = $this->evaluate($zonesById, $zone['id'], $resident);
            if ($access['hidden']) {
                $hiddenZoneIds[] = $zone['id'];

                continue;
            }
            $outsideArea = $area !== [] && ! $this->withinArea($zonesById, $zone['id'], $area);
            $zones[] = [...$zone, 'residentAccess' => $outsideArea && $access['state'] !== self::PRIVATE ? self::OUTSIDE_AREA : $access['state']];
        }

        $visible = clone $region;
        $visible->layout = [
            ...$layout,
            'zones' => $zones,
            'objects' => collect($layout['objects'] ?? [])
                ->reject(fn (array $object) => in_array($object['zoneId'] ?? null, $hiddenZoneIds, true))
                ->values()->all(),
        ];

        return $visible;
    }

    /**
     * What she is told about entering a zone this action annotated; null for
     * a place anyone may enter.
     *
     * @param  array<string, mixed>  $zone
     */
    public function note(array $zone): ?string
    {
        return match ($zone['residentAccess'] ?? self::OPEN) {
            self::ALLOWED => 'private, and you may go in',
            self::PRIVATE => 'private: you go in only when the user asks you to',
            self::OUTSIDE_AREA => 'outside the area you keep to: you go there only when the user asks you to',
            default => null,
        };
    }

    /**
     * Whether the zone is one of the area's zones or lies inside one.
     *
     * @param  array<string, array<string, mixed>>  $zonesById
     * @param  array<int, string>  $area
     */
    private function withinArea(array $zonesById, string $zoneId, array $area): bool
    {
        $seen = [];
        while ($zoneId !== null && isset($zonesById[$zoneId]) && ! isset($seen[$zoneId])) {
            if (in_array($zoneId, $area, true)) {
                return true;
            }
            $seen[$zoneId] = true;
            $zoneId = $zonesById[$zoneId]['parentId'] ?? null;
        }

        return false;
    }

    /**
     * Walks a zone's chain from the outermost zone in. Access granted to a
     * zone covers every zone inside it; a private zone nobody above it opened
     * keeps her out, and a secret one also hides it from her.
     *
     * @param  array<string, array<string, mixed>>  $zonesById
     * @return array{state: string, hidden: bool}
     */
    private function evaluate(array $zonesById, string $zoneId, WorldResident $resident): array
    {
        $chain = [];
        $seen = [];
        while ($zoneId !== null && isset($zonesById[$zoneId]) && ! isset($seen[$zoneId])) {
            $seen[$zoneId] = true;
            array_unshift($chain, $zonesById[$zoneId]);
            $zoneId = $zonesById[$zoneId]['parentId'] ?? null;
        }

        $tags = array_map(mb_strtolower(...), $resident->accessTags());
        $zoneIds = $resident->accessZoneIds();
        $granted = false;
        $restricted = false;
        $blocked = false;
        $hidden = false;

        foreach ($chain as $zone) {
            $zoneTags = array_map(mb_strtolower(...), $zone['accessTags'] ?? []);
            $granted = $granted || in_array($zone['id'], $zoneIds, true) || array_intersect($zoneTags, $tags) !== [];
            if (! ($zone['private'] ?? false)) {
                continue;
            }
            $restricted = true;
            if (! $granted) {
                $blocked = true;
                $hidden = $hidden || ($zone['secret'] ?? false);
            }
        }

        return [
            'state' => $blocked ? self::PRIVATE : ($restricted ? self::ALLOWED : self::OPEN),
            'hidden' => $hidden,
        ];
    }
}
