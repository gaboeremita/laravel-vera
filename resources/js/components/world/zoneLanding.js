import { insideOutline } from './worldLocation.js';

const ISLAND_CHECKS = 40;

export function outlineCentroid(outline) {
	const sum = outline.reduce((total, [x, z]) => ({ x: total.x + x, z: total.z + z }), { x: 0, z: 0 });
	return { x: sum.x / outline.length, z: sum.z / outline.length };
}

/**
 * Where to stand in a zone: the walkable grid point inside its outline and
 * height range closest to the outline's middle, skipping patches of ground
 * that lead nowhere. Null when the zone has no such point or the grid isn't
 * finished.
 */
export function zoneLanding(layout, zoneId, grid) {
	const zone = (layout?.zones ?? []).find((candidate) => candidate.id === zoneId);
	if (!zone || !grid?.isComplete || (zone.outline ?? []).length < 3) return null;
	const middle = outlineCentroid(zone.outline);
	const candidates = [];
	const point = { x: 0, y: 0, z: 0 };
	for (let node = 0; node < grid.heights.length; node++) {
		grid.nodePosition(node, point);
		if (point.y < zone.minY || point.y > zone.maxY || !insideOutline(zone.outline, point.x, point.z)) continue;
		candidates.push({ node, distance: Math.hypot(point.x - middle.x, point.z - middle.z) });
	}
	candidates.sort((a, b) => a.distance - b.distance);
	const checked = candidates.slice(0, ISLAND_CHECKS);
	const best = checked.find((candidate) => !grid.isIsland(candidate.node)) ?? checked[0];
	return best ? { ...grid.nodePosition(best.node, { x: 0, y: 0, z: 0 }) } : null;
}
