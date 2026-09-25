<?php

namespace App\Actions;

use App\Models\World;

class ResolveWorldState
{
    /**
     * @param  array{user?: array{x: float, y: float, z: float}, residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     * @return array{user: ?array{floor: ?array, zone: ?array, zoneChain: array<int, array>}, residents: array<int|string, array{floor: ?array, zone: ?array, zoneChain: array<int, array>, distanceToUser: ?float}>}
     */
    public function handle(World $world, array $positions): array
    {
        $layout = $world->layout ?? [];
        $user = isset($positions['user']) ? $this->locate($layout, $positions['user']) : null;

        $residents = [];
        foreach ($positions['residents'] ?? [] as $residentId => $point) {
            $residents[$residentId] = [
                ...$this->locate($layout, $point),
                'distanceToUser' => isset($positions['user']) ? $this->distance($point, $positions['user']) : null,
            ];
        }

        return ['user' => $user, 'residents' => $residents];
    }

    /**
     * @param  array{x: float, y: float, z: float}  $point
     * @return array{floor: ?array, zone: ?array, zoneChain: array<int, array>}
     */
    public function locate(array $layout, array $point): array
    {
        $zone = $this->zoneAt($layout, $point);

        return [
            'floor' => $this->floorAt($layout, (float) $point['y']),
            'zone' => $zone,
            'zoneChain' => $zone ? $this->zoneChain($layout, $zone) : [],
        ];
    }

    public function floorAt(array $layout, float $y): ?array
    {
        foreach ($layout['floors'] ?? [] as $floor) {
            if ($y >= $floor['minY'] && $y < $floor['maxY']) {
                return $floor;
            }
        }

        return null;
    }

    /**
     * The innermost zone containing the point, judged by height range and ground outline.
     *
     * @param  array{x: float, y: float, z: float}  $point
     */
    public function zoneAt(array $layout, array $point): ?array
    {
        $floor = $this->floorAt($layout, (float) $point['y']);
        $best = null;
        $bestDepth = -1;

        foreach ($layout['zones'] ?? [] as $zone) {
            if ($point['y'] < $zone['minY'] || $point['y'] > $zone['maxY']) {
                continue;
            }
            if ($floor !== null && $zone['floorId'] !== null && $zone['floorId'] !== $floor['id']) {
                continue;
            }
            if (! $this->insideOutline($zone['outline'], (float) $point['x'], (float) $point['z'])) {
                continue;
            }

            $depth = count($this->zoneChain($layout, $zone));
            if ($depth > $bestDepth) {
                $best = $zone;
                $bestDepth = $depth;
            }
        }

        return $best;
    }

    /**
     * The zone and its ancestors, outermost first.
     *
     * @return array<int, array>
     */
    public function zoneChain(array $layout, array $zone): array
    {
        $zonesById = collect($layout['zones'] ?? [])->keyBy('id');
        $chain = [$zone];
        $seen = [$zone['id'] => true];

        while (($parentId = end($chain)['parentId'] ?? null) !== null && $zonesById->has($parentId) && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $chain[] = $zonesById->get($parentId);
        }

        return array_reverse($chain);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $outline
     */
    private function insideOutline(array $outline, float $x, float $z): bool
    {
        $inside = false;
        $count = count($outline);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $zi] = $outline[$i];
            [$xj, $zj] = $outline[$j];

            if ((($zi > $z) !== ($zj > $z)) && ($x < ($xj - $xi) * ($z - $zi) / ($zj - $zi) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  array{x: float, y: float, z: float}  $a
     * @param  array{x: float, y: float, z: float}  $b
     */
    public function distance(array $a, array $b): float
    {
        return sqrt(($a['x'] - $b['x']) ** 2 + ($a['y'] - $b['y']) ** 2 + ($a['z'] - $b['z']) ** 2);
    }
}
