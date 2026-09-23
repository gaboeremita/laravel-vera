export const PLAN_CUT_HEIGHT = 2.2;
const BOUNDS_PADDING = 1.5;

export function floorForHeight(layout, y) {
	return layout?.floors?.find((floor) => y >= floor.minY && y < floor.maxY) ?? null;
}

export function zonesOnFloor(layout, floorId) {
	return (layout?.zones ?? []).filter((zone) => floorId === null || zone.floorId === floorId);
}

export function floorBounds(layout, floorId) {
	const points = zonesOnFloor(layout, floorId).flatMap((zone) => zone.outline);
	if (points.length === 0) return null;
	const xs = points.map(([x]) => x);
	const zs = points.map(([, z]) => z);
	return {
		minX: Math.min(...xs) - BOUNDS_PADDING,
		maxX: Math.max(...xs) + BOUNDS_PADDING,
		minZ: Math.min(...zs) - BOUNDS_PADDING,
		maxZ: Math.max(...zs) + BOUNDS_PADDING,
	};
}

export function floorGroundHeight(layout, floorId, fallback = 0) {
	const heights = zonesOnFloor(layout, floorId).map((zone) => zone.entry.y).sort((a, b) => a - b);
	if (heights.length === 0) return fallback;
	return heights[Math.floor(heights.length / 2)];
}

export function projectToMap(point, bounds, size) {
	return {
		x: ((point.x - bounds.minX) / (bounds.maxX - bounds.minX)) * size.width,
		y: ((point.z - bounds.minZ) / (bounds.maxZ - bounds.minZ)) * size.height,
	};
}

export function spreadLabels(markers, minSpacing) {
	const placed = [];
	for (const marker of [...markers].sort((a, b) => a.y - b.y || a.x - b.x)) {
		let labelY = marker.y;
		while (placed.some((other) => Math.abs(other.labelX - marker.x) < minSpacing && Math.abs(other.labelY - labelY) < minSpacing)) {
			labelY += minSpacing;
		}
		placed.push({ ...marker, labelX: marker.x, labelY });
	}
	return placed;
}
