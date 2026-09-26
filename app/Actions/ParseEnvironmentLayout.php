<?php

namespace App\Actions;

class ParseEnvironmentLayout
{
    private const POSTURES = ['sitting', 'lying', 'reclining'];

    private const TYPES = ['floor', 'zone', 'entry', 'object', 'spot'];

    private const APPROACH_DISTANCE = 0.6;

    /** @var array<int, array{node: string, reason: string}> */
    private array $warnings = [];

    public function __construct(private readonly ResolveWorldState $resolveWorldState) {}

    /**
     * @return array{layout: array{floors: array, zones: array, objects: array}, warnings: array<int, array{node: string, reason: string}>}
     */
    public function handle(string $contents): array
    {
        $this->warnings = [];
        $empty = ['floors' => [], 'zones' => [], 'objects' => []];
        $gltf = $this->readJsonChunk($contents);

        if ($gltf === null) {
            return ['layout' => $empty, 'warnings' => [[
                'node' => 'environment',
                'reason' => 'The environment file is not a valid GLB, so no markers were read.',
            ]]];
        }

        $markers = $this->collectMarkers($gltf['nodes'] ?? [], $this->rootNodes($gltf));

        $layout = ['floors' => $this->floors($markers), 'zones' => [], 'objects' => []];
        $layout['zones'] = $this->zones($markers, $layout['floors']);
        $layout['objects'] = $this->objects($markers, $layout);

        return ['layout' => $layout, 'warnings' => $this->warnings];
    }

    private function readJsonChunk(string $contents): ?array
    {
        if (strlen($contents) < 20 || substr($contents, 0, 4) !== 'glTF') {
            return null;
        }

        $chunk = unpack('VchunkLength/VchunkType', substr($contents, 12, 8));
        if ($chunk['chunkType'] !== 0x4E4F534A || strlen($contents) < 20 + $chunk['chunkLength']) {
            return null;
        }

        $json = json_decode(substr($contents, 20, $chunk['chunkLength']), true);

        return is_array($json) ? $json : null;
    }

    /**
     * @return array<int, int>
     */
    private function rootNodes(array $gltf): array
    {
        $scene = $gltf['scenes'][$gltf['scene'] ?? 0]['nodes'] ?? null;
        if (is_array($scene)) {
            return $scene;
        }

        $children = collect($gltf['nodes'] ?? [])->pluck('children')->filter()->flatten()->all();

        return array_values(array_diff(array_keys($gltf['nodes'] ?? []), $children));
    }

    /**
     * Walks the node tree, composing world matrices and remembering each
     * marker's direct parent and nearest object ancestor.
     *
     * @return array<int, array{index: int, name: string, vera: array, matrix: array<int, float>, parent: ?int, object: ?int}>
     */
    private function collectMarkers(array $nodes, array $roots): array
    {
        $markers = [];
        $stack = array_map(fn (int $index) => [$index, $this->identity(), null, null], array_reverse($roots));

        while ($stack !== []) {
            [$index, $parentMatrix, $parentMarker, $objectMarker] = array_pop($stack);
            $node = $nodes[$index] ?? null;
            if (! is_array($node)) {
                continue;
            }

            $matrix = $this->multiply($parentMatrix, $this->localMatrix($node));
            $vera = $node['extras']['vera'] ?? null;
            $markerIndex = $parentMarker;
            $childObject = $objectMarker;

            if (is_array($vera)) {
                $markers[$index] = [
                    'index' => $index,
                    'name' => (string) ($node['name'] ?? "node {$index}"),
                    'vera' => $vera,
                    'matrix' => $matrix,
                    'parent' => $parentMarker,
                    'object' => $objectMarker,
                ];
                $markerIndex = $index;
                if (($vera['type'] ?? null) === 'object') {
                    $childObject = $index;
                }
            }

            foreach (array_reverse($node['children'] ?? []) as $child) {
                $stack[] = [$child, $matrix, $markerIndex, $childObject];
            }
        }

        foreach ($markers as $index => $marker) {
            if (! in_array($marker['vera']['type'] ?? null, self::TYPES, true)) {
                $this->warn($marker, sprintf('unknown marker type "%s"', $marker['vera']['type'] ?? ''));
                unset($markers[$index]);
            }
        }

        return $markers;
    }

    private function floors(array $markers): array
    {
        $floors = [];

        foreach ($this->ofType($markers, 'floor') as $marker) {
            $vera = $marker['vera'];
            if (! $this->hasFields($marker, ['id', 'name', 'minY', 'maxY']) || ! $this->validId($marker, 'floor', $floors)) {
                continue;
            }
            if (! is_numeric($vera['minY']) || ! is_numeric($vera['maxY']) || $vera['minY'] >= $vera['maxY']) {
                $this->warn($marker, 'floor needs a numeric minY below maxY');

                continue;
            }

            $overlapping = collect($floors)->first(fn (array $floor) => $vera['minY'] < $floor['maxY'] && $floor['minY'] < $vera['maxY']);
            if ($overlapping !== null) {
                $this->warn($marker, sprintf('floor range overlaps floor "%s"', $overlapping['id']));

                continue;
            }

            $floors[] = ['id' => $vera['id'], 'name' => $vera['name'], 'minY' => $vera['minY'], 'maxY' => $vera['maxY']];
        }

        return $floors;
    }

    private function zones(array $markers, array $floors): array
    {
        $floorIds = array_column($floors, 'id');
        $zones = [];

        foreach ($this->ofType($markers, 'zone') as $marker) {
            $vera = $marker['vera'];
            $required = $floors === [] ? ['id', 'name', 'description'] : ['id', 'name', 'description', 'floor'];
            if (! $this->hasFields($marker, $required) || ! $this->validId($marker, 'zone', $zones)) {
                continue;
            }
            if ($floors !== [] && ! in_array($vera['floor'], $floorIds, true)) {
                $this->warn($marker, sprintf('unknown floor "%s"', $vera['floor']));

                continue;
            }

            $entries = array_filter($markers, fn (array $candidate) => $candidate['parent'] === $marker['index'] && ($candidate['vera']['type'] ?? null) === 'entry');
            if ($entries === []) {
                $this->warn($marker, 'zone has no entry child');

                continue;
            }

            $outline = $this->zoneOutline($marker);
            if ($outline === null) {
                continue;
            }

            $activities = $this->activities($marker, $vera['activities'] ?? []);
            $minY = $this->transformPoint($marker['matrix'], [0, (float) ($vera['minY'] ?? -1), 0])[1];
            $maxY = $this->transformPoint($marker['matrix'], [0, (float) ($vera['maxY'] ?? 1), 0])[1];

            $zones[] = [
                'id' => $vera['id'],
                'name' => $vera['name'],
                'description' => $vera['description'],
                'floorId' => $floors === [] ? null : $vera['floor'],
                'parentId' => $vera['parent'] ?? null,
                'private' => (bool) ($vera['private'] ?? false) || (bool) ($vera['secret'] ?? false),
                'secret' => (bool) ($vera['secret'] ?? false),
                'accessTags' => $this->accessTags($marker, $vera['access'] ?? []),
                'outline' => $outline,
                'minY' => min($minY, $maxY),
                'maxY' => max($minY, $maxY),
                'entry' => $this->position(reset($entries)['matrix']),
                'activities' => $activities,
                'node' => $marker,
            ];
        }

        $zoneIds = array_column($zones, 'id');
        $valid = [];
        foreach ($zones as $zone) {
            if ($zone['parentId'] !== null && ! in_array($zone['parentId'], $zoneIds, true)) {
                $this->warn($zone['node'], sprintf('unknown parent zone "%s"', $zone['parentId']));

                continue;
            }
            unset($zone['node']);
            $valid[] = $zone;
        }

        return $valid;
    }

    /**
     * @return ?array<int, array{0: float, 1: float}>
     */
    private function zoneOutline(array $marker): ?array
    {
        $local = $marker['vera']['outline'] ?? [[-1, -1], [1, -1], [1, 1], [-1, 1]];
        $valid = is_array($local) && count($local) >= 3
            && collect($local)->every(fn ($point) => is_array($point) && count($point) === 2 && is_numeric($point[0]) && is_numeric($point[1]));

        if (! $valid) {
            $this->warn($marker, 'outline needs at least three points');

            return null;
        }

        return array_map(function (array $point) use ($marker) {
            [$x, , $z] = $this->transformPoint($marker['matrix'], [(float) $point[0], 0, (float) $point[1]]);

            return [round($x, 6), round($z, 6)];
        }, $local);
    }

    private function objects(array $markers, array $layout): array
    {
        $objects = [];
        $spotIds = [];

        foreach ($this->ofType($markers, 'spot') as $marker) {
            if ($marker['object'] === null) {
                $this->warn($marker, 'spot is not inside an object');
            }
        }

        foreach ($this->ofType($markers, 'object') as $marker) {
            $vera = $marker['vera'];
            if (! $this->hasFields($marker, ['id', 'name', 'description']) || ! $this->validId($marker, 'object', $objects)) {
                continue;
            }

            $position = $this->position($marker['matrix']);
            $zone = $this->resolveWorldState->zoneAt($layout, $position);

            $objects[] = [
                'id' => $vera['id'],
                'name' => $vera['name'],
                'description' => $vera['description'],
                'position' => $position,
                'zoneId' => $zone['id'] ?? null,
                'spots' => $this->spots($markers, $marker, $spotIds),
            ];
        }

        return $objects;
    }

    private function spots(array $markers, array $object, array &$spotIds): array
    {
        $spots = [];

        foreach ($this->ofType($markers, 'spot') as $marker) {
            if ($marker['object'] !== $object['index']) {
                continue;
            }
            if (! $this->hasFields($marker, ['id'])) {
                continue;
            }
            if (! $this->validId($marker, 'spot', array_map(fn (string $id) => ['id' => $id], $spotIds))) {
                continue;
            }

            $activities = $this->activities($marker, $marker['vera']['activities'] ?? []);
            if ($activities === []) {
                $this->warn($marker, 'spot has no activities');

                continue;
            }

            $capacity = $marker['vera']['capacity'] ?? 1;
            if (! is_int($capacity) || $capacity < 1) {
                $this->warn($marker, 'spot capacity must be a whole number of at least 1');
                $capacity = 1;
            }

            $position = $this->position($marker['matrix']);
            [$dx, , $dz] = $this->transformDirection($marker['matrix'], [0, 0, 1]);
            $facing = atan2($dx, $dz);

            $spotIds[] = $marker['vera']['id'];
            $spots[] = [
                'id' => $marker['vera']['id'],
                'position' => $position,
                'facing' => round($facing, 6),
                'approach' => [
                    'x' => round($position['x'] + sin($facing) * self::APPROACH_DISTANCE, 6),
                    'y' => $position['y'],
                    'z' => round($position['z'] + cos($facing) * self::APPROACH_DISTANCE, 6),
                ],
                'activities' => $activities,
                'capacity' => $capacity,
            ];
        }

        return $spots;
    }

    /**
     * @return array<int, array{id: string, name: string, posture: ?string, pose: ?string}>
     */
    private function activities(array $marker, mixed $activities): array
    {
        $valid = [];

        foreach (is_array($activities) ? $activities : [] as $activity) {
            if (! is_array($activity) || empty($activity['id']) || empty($activity['name'])) {
                $this->warn($marker, 'activity is missing id or name');

                continue;
            }
            if (isset($activity['posture']) && ! in_array($activity['posture'], self::POSTURES, true)) {
                $this->warn($marker, sprintf('unknown posture "%s"', $activity['posture']));

                continue;
            }

            $valid[] = [
                'id' => (string) $activity['id'],
                'name' => (string) $activity['name'],
                'posture' => $activity['posture'] ?? null,
                'pose' => isset($activity['pose']) ? (string) $activity['pose'] : null,
            ];
        }

        return $valid;
    }

    /**
     * The resident groups a private zone lets in, from its `access` list.
     *
     * @return array<int, string>
     */
    private function accessTags(array $marker, mixed $tags): array
    {
        if (! is_array($tags) || ! array_is_list($tags)) {
            $this->warn($marker, 'access must be a list of tags');

            return [];
        }

        $valid = [];
        foreach ($tags as $tag) {
            if (! is_string($tag) || trim($tag) === '') {
                $this->warn($marker, 'access tags must be non-empty strings');

                continue;
            }
            $valid[] = trim($tag);
        }

        return array_values(array_unique($valid));
    }

    private function ofType(array $markers, string $type): array
    {
        return array_filter($markers, fn (array $marker) => ($marker['vera']['type'] ?? null) === $type);
    }

    private function hasFields(array $marker, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! isset($marker['vera'][$field]) || $marker['vera'][$field] === '') {
                $this->warn($marker, sprintf('missing required field "%s"', $field));

                return false;
            }
        }

        return true;
    }

    private function validId(array $marker, string $type, array $existing): bool
    {
        $id = $marker['vera']['id'];

        if (! is_string($id) || preg_match('/^[a-z0-9-]+$/', $id) !== 1) {
            $this->warn($marker, sprintf('id "%s" must be a lowercase slug', is_scalar($id) ? $id : ''));

            return false;
        }
        if (in_array($id, array_column($existing, 'id'), true)) {
            $this->warn($marker, sprintf('duplicate %s id "%s"', $type, $id));

            return false;
        }

        return true;
    }

    private function warn(array $marker, string $reason): void
    {
        $this->warnings[] = ['node' => $marker['name'], 'reason' => $reason];
    }

    /**
     * @return array{x: float, y: float, z: float}
     */
    private function position(array $matrix): array
    {
        return ['x' => round($matrix[12], 6), 'y' => round($matrix[13], 6), 'z' => round($matrix[14], 6)];
    }

    /**
     * Column-major 4x4 matrix for a glTF node, from `matrix` or translation, rotation and scale.
     *
     * @return array<int, float>
     */
    private function localMatrix(array $node): array
    {
        if (isset($node['matrix']) && count($node['matrix']) === 16) {
            return array_map('floatval', $node['matrix']);
        }

        [$tx, $ty, $tz] = $node['translation'] ?? [0, 0, 0];
        [$qx, $qy, $qz, $qw] = $node['rotation'] ?? [0, 0, 0, 1];
        [$sx, $sy, $sz] = $node['scale'] ?? [1, 1, 1];

        return [
            (1 - 2 * ($qy * $qy + $qz * $qz)) * $sx, (2 * ($qx * $qy + $qz * $qw)) * $sx, (2 * ($qx * $qz - $qy * $qw)) * $sx, 0,
            (2 * ($qx * $qy - $qz * $qw)) * $sy, (1 - 2 * ($qx * $qx + $qz * $qz)) * $sy, (2 * ($qy * $qz + $qx * $qw)) * $sy, 0,
            (2 * ($qx * $qz + $qy * $qw)) * $sz, (2 * ($qy * $qz - $qx * $qw)) * $sz, (1 - 2 * ($qx * $qx + $qy * $qy)) * $sz, 0,
            (float) $tx, (float) $ty, (float) $tz, 1,
        ];
    }

    /**
     * @return array<int, float>
     */
    private function identity(): array
    {
        return [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1];
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     * @return array<int, float>
     */
    private function multiply(array $a, array $b): array
    {
        $result = [];
        for ($column = 0; $column < 4; $column++) {
            for ($row = 0; $row < 4; $row++) {
                $sum = 0.0;
                for ($k = 0; $k < 4; $k++) {
                    $sum += $a[$k * 4 + $row] * $b[$column * 4 + $k];
                }
                $result[$column * 4 + $row] = $sum;
            }
        }

        return $result;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function transformPoint(array $matrix, array $point): array
    {
        [$x, $y, $z] = $point;

        return [
            $matrix[0] * $x + $matrix[4] * $y + $matrix[8] * $z + $matrix[12],
            $matrix[1] * $x + $matrix[5] * $y + $matrix[9] * $z + $matrix[13],
            $matrix[2] * $x + $matrix[6] * $y + $matrix[10] * $z + $matrix[14],
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function transformDirection(array $matrix, array $direction): array
    {
        [$x, $y, $z] = $direction;

        return [
            $matrix[0] * $x + $matrix[4] * $y + $matrix[8] * $z,
            $matrix[1] * $x + $matrix[5] * $y + $matrix[9] * $z,
            $matrix[2] * $x + $matrix[6] * $y + $matrix[10] * $z,
        ];
    }
}
