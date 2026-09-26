/** How many of a world's lights are lit at once; the rest wait until the user is closer to them than to these. */
export const LIGHT_BUDGET = 6;

/** The indices of the `count` lights nearest the point, nearest first. */
export function nearestLights(positions, point, count = LIGHT_BUDGET) {
	return positions
		.map((position, index) => ({ index, distance: (position.x - point.x) ** 2 + (position.y - point.y) ** 2 + (position.z - point.z) ** 2 }))
		.sort((a, b) => a.distance - b.distance)
		.slice(0, count)
		.map((light) => light.index);
}

/**
 * Whether a group tagged with the floors it can be seen from is drawn for a
 * user on this floor. An untagged group, or a user whose floor is not known,
 * is always drawn.
 */
export function visibleFromFloor(visibleFrom, floorId) {
	if (!Array.isArray(visibleFrom) || visibleFrom.length === 0 || !floorId) return true;
	return visibleFrom.includes(floorId);
}
