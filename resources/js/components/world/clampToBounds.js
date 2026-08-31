const MARGIN = 0.3;

export function clampToBounds(x, y, z, bounds) {
	if (!bounds || bounds.isEmpty()) return { x, y, z };
	return {
		x: Math.min(Math.max(x, bounds.min.x + MARGIN), bounds.max.x - MARGIN),
		y: Math.min(Math.max(y, bounds.min.y), bounds.max.y),
		z: Math.min(Math.max(z, bounds.min.z + MARGIN), bounds.max.z - MARGIN),
	};
}
