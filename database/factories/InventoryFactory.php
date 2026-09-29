<?php

namespace Database\Factories;

use App\Enums\InventoryHolder;
use App\Models\Inventory;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'holder' => InventoryHolder::Player,
            'credits' => 0,
        ];
    }

    public function forPlayer(): static
    {
        return $this->state(fn () => ['holder' => InventoryHolder::Player]);
    }

    public function forResident(WorldResident $resident): static
    {
        return $this->state(fn () => ['holder' => InventoryHolder::Resident, 'world_resident_id' => $resident->id, 'credits' => null]);
    }

    public function forObject(int $regionId, string $objectId): static
    {
        return $this->state(fn () => ['holder' => InventoryHolder::Object, 'region_id' => $regionId, 'object_id' => $objectId]);
    }

    public function unlimited(): static
    {
        return $this->state(fn () => ['credits' => null]);
    }
}
