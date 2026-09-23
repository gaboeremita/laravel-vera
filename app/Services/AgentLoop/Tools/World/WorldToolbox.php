<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Models\World;
use RuntimeException;

class WorldToolbox
{
    /**
     * @var ?array{verb: string, target: ?string, activity: ?string, steps?: array<int, array<string, ?string>>}
     */
    private ?array $chosenAction = null;

    /**
     * @param  array<int, array<string, mixed>>  $residentZoneChain  the zone she stands in and the zones around it; empty when her position is unknown
     * @param  array<int, string>  $occupiedSpots  spot ids other residents are using
     * @param  array<int, string>  $poseNames  names of the poses in her library, in any posture
     */
    public function __construct(
        public readonly World $world,
        public readonly array $residentZoneChain = [],
        public readonly array $occupiedSpots = [],
        public readonly array $poseNames = [],
    ) {}

    /**
     * @return AgentTool[]
     */
    public function tools(): array
    {
        $tools = [
            new WhereCanITool($this),
            new WhatIsInTool($this),
            new DescribeTool($this),
            new GoToTool($this),
            new FollowTool($this),
            new StopTool($this),
        ];

        if ($this->spots() !== []) {
            $tools[] = new UseTool($this);
        }

        if ($this->zoneActivityIds() !== []) {
            $tools[] = new ZoneTool($this);
        }

        $tools[] = new PlanTool($this);

        return $tools;
    }

    /**
     * @return ?array{verb: string, target: ?string, activity: ?string, steps?: array<int, array<string, ?string>>}
     */
    public function chosenAction(): ?array
    {
        return $this->chosenAction;
    }

    /**
     * @param  array{verb: string, target: ?string, activity: ?string, steps?: array<int, array<string, ?string>>}  $action
     */
    public function choose(array $action): void
    {
        if ($this->chosenAction !== null) {
            throw new RuntimeException(sprintf(
                'You already chose an action this turn: %s. It stands; one action per turn.',
                trim("{$this->chosenAction['verb']} {$this->chosenAction['target']}"),
            ));
        }

        $this->chosenAction = $action;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function zones(): array
    {
        return $this->world->layout['zones'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function objects(): array
    {
        return $this->world->layout['objects'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function targetIds(): array
    {
        return collect($this->zones())->merge($this->objects())->pluck('id')->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function spots(): array
    {
        return collect($this->objects())->flatMap(fn (array $object) => collect($object['spots'])->map(fn (array $spot) => [...$spot, 'objectId' => $object['id'], 'objectName' => $object['name']]))->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public function zoneActivityIds(): array
    {
        return collect($this->zones())->flatMap(fn (array $zone) => collect($zone['activities'])->pluck('id'))->unique()->values()->all();
    }

    /**
     * The pose she plays for an activity: the one she chose from her own
     * library, else the activity's own pose when she has one by that name.
     *
     * @param  array<string, mixed>  $activity
     */
    public function poseForActivity(array $activity, string $chosen): ?string
    {
        if ($chosen !== '') {
            $pose = collect($this->poseNames)->first(fn (string $name) => $this->sameName($name, $chosen));
            if ($pose === null) {
                throw new RuntimeException(sprintf('You have no pose called "%s". Your poses: %s.', $chosen, implode(', ', $this->poseNames)));
            }

            return $pose;
        }

        $own = $activity['pose'] ?? null;

        return $own === null ? null : collect($this->poseNames)->first(fn (string $name) => $this->sameName($name, $own));
    }

    /**
     * @return array<string, mixed>
     */
    public function poseParameter(): array
    {
        $parameter = ['type' => 'string', 'description' => 'The pose of yours that fits this activity best, when its own pose is named differently from yours. Leave it out to use the activity\'s own pose.'];

        return $this->poseNames === [] ? $parameter : [...$parameter, 'enum' => $this->poseNames];
    }

    public function findSpot(string $written): ?array
    {
        return collect($this->spots())->first(fn (array $spot) => $this->sameName($spot['id'], $written));
    }

    /**
     * The zones a place or thing is in, from the innermost out.
     *
     * @return array<int, array<string, mixed>>
     */
    public function zoneChainOf(?string $zoneId): array
    {
        $chain = [];
        while ($zoneId !== null && ($zone = collect($this->zones())->firstWhere('id', $zoneId)) !== null) {
            $chain[] = $zone;
            $zoneId = $zone['parentId'];
        }

        return $chain;
    }

    public function findZone(string $written): ?array
    {
        return collect($this->zones())->first(fn (array $zone) => $this->sameName($zone['id'], $written) || $this->sameName($zone['name'], $written));
    }

    public function findObject(string $written): ?array
    {
        return collect($this->objects())->first(fn (array $object) => $this->sameName($object['id'], $written) || $this->sameName($object['name'], $written));
    }

    public function floorName(?string $floorId): ?string
    {
        return collect($this->world->layout['floors'] ?? [])->firstWhere('id', $floorId)['name'] ?? null;
    }

    /**
     * @return array{name: string, id: string}|null
     */
    public function placeReference(?string $zoneId): ?array
    {
        $zone = collect($this->zones())->firstWhere('id', $zoneId);

        return $zone !== null ? ['name' => $zone['name'], 'id' => $zone['id']] : null;
    }

    /**
     * Models write ids loosely ("pool_terrace", "Pool Terrace"), so ids and
     * names compare case-insensitively with underscores and spaces as hyphens.
     */
    public function sameName(string $known, string $written): bool
    {
        return $this->normalize($known) === $this->normalize($written);
    }

    public function normalize(string $value): string
    {
        return trim(preg_replace('/[\s_-]+/u', '-', mb_strtolower($value)), '-');
    }
}
