export const PLAN_CUT_HEIGHT = 2.2;
const BOUNDS_PADDING = 1.5;

export function floorForHeight(layout, y) {
	return layout?.floors?.find((floor) => y >= floor.minY && y < floor.maxY) ?? null;
}

export function zonesOnFloor(layout, floorId) {
	return (layout?.zones ?? []).filter((zone) => floorId === null || zone.floorId === floorId);
}

/** The rooms named on a floor's map: every zone except those holding other zones on that floor, whose name would cover their rooms. */
export function labelledZones(layout, floorId) {
	const zones = zonesOnFloor(layout, floorId);
	const parentIds = new Set(zones.map((zone) => zone.parentId).filter(Boolean));
	return zones.filter((zone) => !parentIds.has(zone.id));
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

/** Metres the corner map shows across, around the user. */
export const MINIMAP_SPAN = 50;

function windowAxis(min, max, center, span) {
	if (max - min <= span) {
		const middle = (min + max) / 2;
		return [middle - span / 2, middle + span / 2];
	}
	const start = Math.min(Math.max(center - span / 2, min), max - span);
	return [start, start + span];
}

/**
 * The part of a floor the corner map shows: a square around the point, kept
 * inside the floor, or the whole floor when it fits in that square.
 */
export function minimapWindow(bounds, point, span = MINIMAP_SPAN) {
	if (bounds.maxX - bounds.minX <= span && bounds.maxZ - bounds.minZ <= span) return bounds;
	const [minX, maxX] = windowAxis(bounds.minX, bounds.maxX, point?.x ?? (bounds.minX + bounds.maxX) / 2, span);
	const [minZ, maxZ] = windowAxis(bounds.minZ, bounds.maxZ, point?.z ?? (bounds.minZ + bounds.maxZ) / 2, span);
	return { minX, maxX, minZ, maxZ };
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

/** Pushes wide labels down until none overlaps another; each label carries its width, all share one height. */
export function spreadWideLabels(labels, height) {
	const placed = [];
	for (const label of [...labels].sort((a, b) => a.y - b.y || a.x - b.x)) {
		let labelY = label.y;
		const overlaps = (other) => Math.abs(other.labelX - label.x) < (other.width + label.width) / 2 && Math.abs(other.labelY - labelY) < height;
		while (placed.some(overlaps)) {
			labelY += height;
		}
		placed.push({ ...label, labelX: label.x, labelY });
	}
	return placed;
}
