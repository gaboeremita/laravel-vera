<?php

namespace App\Actions;

use App\Models\ActivityTerms;
use App\Models\InventoryItem;
use App\Models\Region;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Support\Collection;

class BuildVendorsPrompt
{
    public function __construct(
        private readonly ResolveInventory $resolveInventory,
        private readonly ResolveResidentRegion $resolveResidentRegion,
        private readonly ResolveWorldState $resolveWorldState,
    ) {}

    /**
     * The other residents of her region who sell something: where they serve,
     * where they are now and what they sell. Null when nobody there sells anything.
     *
     * @param  ?array{residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     */
    public function handle(WorldSession $session, Region $region, WorldResident $resident, ?array $positions): ?string
    {
        $stands = $this->standsByVendor($region);

        $vendors = $session->worldUser->world->residents()->with('assistant')->whereKeyNot($resident->id)->get()
            ->filter(fn (WorldResident $other) => $this->resolveResidentRegion->handle($session, $other)->is($region))
            ->map(fn (WorldResident $other) => $this->describeVendor($session, $region, $other, $stands->get($other->id, collect()), $positions['residents'][$other->id] ?? null))
            ->filter()
            ->values();

        if ($vendors->isEmpty()) {
            return null;
        }

        return '- '.$vendors->implode("\n- ")."\nWhen you feel like something one of them sells, go to them and talk to them to get it. It costs you nothing: you only play at paying.";
    }

    /**
     * @param  Collection<int, string>  $stands
     * @param  ?array{x: float, y: float, z: float}  $point
     */
    private function describeVendor(WorldSession $session, Region $region, WorldResident $vendor, Collection $stands, ?array $point): ?string
    {
        $goods = $this->resolveInventory->forResident($session, $vendor)->items()->where('for_sale', true)->with('item')->get()
            ->map(fn (InventoryItem $held) => $held->item->name)
            ->sort()
            ->values();
        if ($goods->isEmpty()) {
            return null;
        }

        $zone = $point !== null ? $this->resolveWorldState->locate($region->layout ?? [], $point)['zone'] : null;
        $where = collect([
            $stands->isNotEmpty() ? 'serves at the '.$stands->implode(' and the ') : null,
            $zone !== null ? "now in {$zone['name']}" : null,
        ])->filter()->implode(', ');

        return $vendor->assistant->name.($where !== '' ? " ({$where})" : '').': '.$goods->implode(', ');
    }

    /**
     * The names of the things each vendor serves at, by resident id.
     *
     * @return Collection<int, Collection<int, string>>
     */
    private function standsByVendor(Region $region): Collection
    {
        return $region->activityTerms()->whereNotNull('vendor_resident_id')->get()
            ->groupBy('vendor_resident_id')
            ->map(fn (Collection $terms) => $terms
                ->map(fn (ActivityTerms $activityTerms) => $region->layoutObject($activityTerms->object_id)['name'] ?? null)
                ->filter()
                ->unique()
                ->values());
    }
}
