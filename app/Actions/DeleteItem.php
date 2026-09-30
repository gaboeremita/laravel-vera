<?php

namespace App\Actions;

use App\Actions\Activities\ResponseItems;
use App\Models\ActivityTerms;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteItem
{
    /**
     * Removes the item from every list that names it by id, then deletes it;
     * inventories and starting inventories follow through their foreign keys.
     */
    public function handle(Item $item): void
    {
        DB::transaction(function () use ($item): void {
            $withoutItem = fn (?array $entries) => collect($entries ?? [])->reject(fn (array $entry) => (int) $entry['itemId'] === $item->id)->values()->all();

            ActivityTerms::whereIn('region_id', $item->world->regions()->select('id'))->get()
                ->each(fn (ActivityTerms $terms) => $terms->update(['responses' => app(ResponseItems::class)->without($terms->responseList(), $item->id)]));
            Item::where('world_id', $item->world_id)->whereKeyNot($item->id)->get()
                ->each(fn (Item $other) => $other->update(['releases_items' => $withoutItem($other->releases_items)]));

            if ($item->cardImage !== null) {
                Storage::disk($item->cardImage->disk)->delete($item->cardImage->path);
                $item->cardImage->delete();
            }
            $sound = $item->sound;
            $item->delete();
            if ($sound !== null) {
                app(StoreSound::class)->releaseIfUnused($sound);
            }
        });
    }

    /**
     * How many places use the item: inventories, starting inventories and activity terms.
     */
    public function usage(Item $item): int
    {
        $named = fn (?array $entries) => collect($entries ?? [])->contains(fn (array $entry) => (int) $entry['itemId'] === $item->id);

        return DB::table('inventory_items')->where('item_id', $item->id)->count()
            + DB::table('starting_inventory_items')->where('item_id', $item->id)->count()
            + ActivityTerms::whereIn('region_id', $item->world->regions()->select('id'))->get()
                ->filter(fn (ActivityTerms $terms) => in_array($item->id, app(ResponseItems::class)->ids($terms->responseList()), true))->count()
            + Item::where('world_id', $item->world_id)->whereKeyNot($item->id)->get()->filter(fn (Item $other) => $named($other->releases_items))->count();
    }
}
