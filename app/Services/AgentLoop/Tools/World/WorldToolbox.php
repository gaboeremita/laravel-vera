<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Models\World;
use RuntimeException;

class WorldToolbox
{
    /**
     * @var ?array{verb: string, target: ?string, activity: ?string}
     */
    private ?array $chosenAction = null;

    /**
     * @param  array<int, array<string, mixed>>  $residentZoneChain  the zone she stands in and the zones around it; empty when her position is unknown
     */
    public function __construct(public readonly World $world, public readonly array $residentZoneChain = []) {}

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

        return $tools;
    }

    /**
     * @return ?array{verb: string, target: ?string, activity: ?string}
     */
    public function chosenAction(): ?array
    {
        return $this->chosenAction;
    }

    /**
     * @param  array{verb: string, target: ?string, activity: ?string}  $action
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

    public function findSpot(string $written): ?array
    {
        return collect($this->spots())->first(fn (array $spot) => $this->sameName($spot['id'], $written));
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
