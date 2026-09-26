<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\ApplyResidentZoneAccess;
use App\Contracts\AgentTool;
use App\Models\World;
use Closure;
use RuntimeException;

class WorldToolbox
{
    public const USER_TARGET = 'user';

    /**
     * @var ?array{verb: string, target: ?string, activity: ?string, line?: string, steps?: array<int, array<string, ?string>>}
     */
    private ?array $chosenAction = null;

    /**
     * @param  array<int, array<string, mixed>>  $residentZoneChain  the zone she stands in and the zones around it; empty when her position is unknown
     * @param  array<int, string>  $occupiedSpots  spot ids other residents are using
     * @param  array<string, array<int, string>>  $posePostures  the postures each of her poses exists in, by pose name
     * @param  ?array<string, int>  $companions  the other residents she can start talking to, resident id by name; null where she is already talking to someone
     * @param  bool  $userAvailable  whether the user is free to be talked to
     * @param  bool  $userInSight  whether the user is in the same room as her
     * @param  ?array{x: float, y: float, z: float}  $residentPoint  where she is; null when unknown
     * @param  ?Closure(): ?array{from: string, memory: string}  $recall  brings back one of her memories
     */
    public function __construct(
        public readonly World $world,
        public readonly array $residentZoneChain = [],
        public readonly array $occupiedSpots = [],
        public readonly array $posePostures = [],
        public readonly ?array $companions = null,
        public readonly bool $userAvailable = true,
        public readonly bool $userInSight = true,
        public readonly ?array $residentPoint = null,
        private readonly ?Closure $recall = null,
    ) {
        $this->poseNames = array_keys($posePostures);
    }

    /**
     * @var array<int, string>
     */
    public readonly array $poseNames;

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

        $tools[] = new SwimToEdgeTool($this);
        $tools[] = new WanderTool($this);
        $tools[] = new PlanTool($this);

        if ($this->companions !== null && ($this->companions !== [] || ($this->userAvailable && $this->userInSight))) {
            $tools[] = new TalkToTool($this);
        }

        return $tools;
    }

    /**
     * Her tools when she decides on her own what to do next: the world tools,
     * plus thinking and remembering where she is.
     *
     * @return AgentTool[]
     */
    public function idleTools(): array
    {
        return [...$this->tools(), new ThinkTool($this), new RememberTool($this)];
    }

    /**
     * @return ?array{from: string, memory: string}
     */
    public function recallMemory(): ?array
    {
        return $this->recall !== null ? ($this->recall)() : null;
    }

    /**
     * @return ?array{verb: string, target: ?string, activity: ?string, line?: string, steps?: array<int, array<string, ?string>>}
     */
    public function chosenAction(): ?array
    {
        return $this->chosenAction;
    }

    /**
     * @param  array{verb: string, target: ?string, activity: ?string, line?: string, steps?: array<int, array<string, ?string>>}  $action
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
        return collect($this->zones())->merge($this->objects())->merge($this->spots())->pluck('id')->all();
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
     * It must have a version for the activity's posture, since an activity
     * never stands her up; a null posture accepts any of her poses.
     *
     * @param  array<string, mixed>  $activity
     */
    public function poseForActivity(array $activity, string $chosen, ?string $posture = 'activity'): ?string
    {
        $posture = $posture === 'activity' ? ($activity['posture'] ?? 'standing') : $posture;
        $fits = fn (string $name) => $posture === null || in_array($posture, $this->posePostures[$name] ?? [], true);

        if ($chosen !== '') {
            $pose = collect($this->poseNames)->first(fn (string $name) => $this->sameName($name, $chosen));
            if ($pose === null) {
                throw new RuntimeException(sprintf('You have no pose called "%s". Your poses: %s.', $chosen, implode(', ', $this->poseNames)));
            }
            if (! $fits($pose)) {
                $fitting = collect($this->poseNames)->filter($fits)->reject(fn (string $name) => $name === 'default');
                throw new RuntimeException(sprintf(
                    'Your pose "%s" has no %s version, and this activity keeps you %s. %s',
                    $pose,
                    $posture,
                    $posture,
                    $fitting->isEmpty() ? 'Leave the pose out.' : 'Your '.$posture.' poses: '.$fitting->implode(', ').'; or leave the pose out.',
                ));
            }

            return $pose;
        }

        $own = $activity['pose'] ?? null;

        return $own === null ? null : collect($this->poseNames)->first(fn (string $name) => $this->sameName($name, $own) && $fits($name));
    }

    /**
     * @return array<string, mixed>
     */
    public function poseParameter(): array
    {
        $parameter = ['type' => 'string', 'description' => 'The pose of yours that fits this activity best, when its own pose is named differently from yours. It must be a pose you have in the posture the activity puts you in (a sitting pose for sitting down). Leave it out to use the activity\'s own pose.'];

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

    /**
     * The place or thing she walks to for an id she wrote; a spot takes her
     * to the thing it belongs to.
     *
     * @return ?array<string, mixed>
     */
    public function findTarget(string $written): ?array
    {
        return $this->findZone($written) ?? $this->findObject($written) ?? $this->findObject($this->findSpot($written)['objectId'] ?? '');
    }

    /**
     * How far a point is from her, in meters; null when her position is unknown.
     *
     * @param  array{x: float, y: float, z: float}  $point
     */
    public function distanceTo(array $point): ?float
    {
        if ($this->residentPoint === null) {
            return null;
        }

        return sqrt(($point['x'] - $this->residentPoint['x']) ** 2 + ($point['y'] - $this->residentPoint['y']) ** 2 + ($point['z'] - $this->residentPoint['z']) ** 2);
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
     * Whether she may enter a place on her own; null when anyone may.
     *
     * @param  array<string, mixed>  $zone
     */
    public function accessNote(array $zone): ?string
    {
        return app(ApplyResidentZoneAccess::class)->note($zone);
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
