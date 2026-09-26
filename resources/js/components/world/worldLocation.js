export function floorAt(layout, y) {
	return layout?.floors?.find((floor) => y >= floor.minY && y < floor.maxY) ?? null;
}

function insideOutline(outline, x, z) {
	let inside = false;
	for (let i = 0, j = outline.length - 1; i < outline.length; j = i++) {
		const [xi, zi] = outline[i];
		const [xj, zj] = outline[j];
		if ((zi > z) !== (zj > z) && x < ((xj - xi) * (z - zi)) / (zj - zi) + xi) inside = !inside;
	}
	return inside;
}

/** The zone and its ancestors, outermost first. */
/**
 * Whether two points are on the same floor; a world without floors, or a
 * point that isn't known, counts as the same floor.
 */
export function onSameFloor(layout, a, b) {
	if (!a || !b || !(layout?.floors?.length)) return true;
	return (floorAt(layout, a.y)?.id ?? null) === (floorAt(layout, b.y)?.id ?? null);
}

export function zoneChain(layout, zone) {
	const zonesById = new Map((layout?.zones ?? []).map((candidate) => [candidate.id, candidate]));
	const chain = [zone];
	const seen = new Set([zone.id]);
	let parentId = zone.parentId ?? null;
	while (parentId !== null && zonesById.has(parentId) && !seen.has(parentId)) {
		seen.add(parentId);
		chain.push(zonesById.get(parentId));
		parentId = zonesById.get(parentId).parentId ?? null;
	}
	return chain.reverse();
}

/** The innermost zone containing the point, judged by height range and ground outline. */
export function zoneAt(layout, point) {
	const floor = floorAt(layout, point.y);
	let best = null;
	let bestDepth = -1;
	for (const zone of layout?.zones ?? []) {
		if (point.y < zone.minY || point.y > zone.maxY) continue;
		if (floor !== null && zone.floorId !== null && zone.floorId !== floor.id) continue;
		if (!insideOutline(zone.outline, point.x, point.z)) continue;
		const depth = zoneChain(layout, zone).length;
		if (depth > bestDepth) {
			best = zone;
			bestDepth = depth;
		}
	}
	return best;
}

/**
 * Tracks which zone the user is in and decides which crossings deserve a
 * title card: re-entering a zone left moments ago does not.
 */
export function createCrossingTracker({ debounceMs }) {
	let current;
	const leftAt = new Map();

	return {
		update(zoneId, now) {
			if (current === undefined) {
				current = zoneId;
				return { changed: true, announce: zoneId !== null };
			}
			if (zoneId === current) return { changed: false, announce: false };
			if (current !== null) leftAt.set(current, now);
			const recentlyLeft = zoneId !== null && leftAt.has(zoneId) && now - leftAt.get(zoneId) < debounceMs;
			current = zoneId;
			return { changed: true, announce: zoneId !== null && !recentlyLeft };
		},
	};
}
