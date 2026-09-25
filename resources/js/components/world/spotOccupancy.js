/** How far above the one below each extra body in a shared spot lies. */
export const STACK_HEIGHT = 0.3;

export function spotCapacity(spot) {
	return spot?.capacity ?? 1;
}

/** Who holds a spot, in the order they took it. */
export function holdersOf(occupiedSpots, spotId) {
	return occupiedSpots.get(spotId) ?? [];
}

/** Whether `holder` already holds the spot or there is room left in it. */
export function hasRoomFor(occupiedSpots, spot, holder) {
	const holders = holdersOf(occupiedSpots, spot.id);
	return holders.includes(holder) || holders.length < spotCapacity(spot);
}

export function claimSpot(occupiedSpots, spot, holder) {
	if (!hasRoomFor(occupiedSpots, spot, holder)) return false;
	const holders = holdersOf(occupiedSpots, spot.id);
	if (!holders.includes(holder)) occupiedSpots.set(spot.id, [...holders, holder]);
	return true;
}

export function releaseSpot(occupiedSpots, spotId, holder) {
	const remaining = holdersOf(occupiedSpots, spotId).filter((current) => current !== holder);
	if (remaining.length > 0) occupiedSpots.set(spotId, remaining);
	else occupiedSpots.delete(spotId);
}

export function releaseAllSpots(occupiedSpots, holder) {
	for (const spotId of [...occupiedSpots.keys()]) releaseSpot(occupiedSpots, spotId, holder);
}

/** 0 for the body resting on the spot itself, 1 for the one on top of it. */
export function stackTier(occupiedSpots, spotId, holder) {
	return Math.max(0, holdersOf(occupiedSpots, spotId).indexOf(holder));
}

/** Who lies directly beneath `holder` in a shared spot, or null. */
export function holderUnder(occupiedSpots, spotId, holder) {
	const holders = holdersOf(occupiedSpots, spotId);
	const index = holders.indexOf(holder);
	return index > 0 ? holders[index - 1] : null;
}

/** Shared spots with more than one body in them, bottom first. */
export function stackedSpots(occupiedSpots) {
	return [...occupiedSpots].filter(([, holders]) => holders.length > 1).map(([spotId, holders]) => ({ spotId, holders: holders.map(String) }));
}

/** Ids of the spots in the layout with no room left for `holder`. */
export function fullSpotIds(occupiedSpots, layout, holder) {
	const spots = new Map((layout?.objects ?? []).flatMap((object) => object.spots.map((spot) => [spot.id, spot])));
	return [...occupiedSpots.keys()].filter((spotId) => !hasRoomFor(occupiedSpots, spots.get(spotId) ?? { id: spotId }, holder));
}
