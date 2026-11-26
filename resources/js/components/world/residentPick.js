export const PICK_RADIUS = 0.4;
export const PICK_HEIGHT = 1.9;

/** Pixels the pointer may travel between press and release for the press to still count as a click. */
export const CLICK_SLOP = 5;

/**
 * Where along a ray it enters an upright cylinder standing on `foot`, or
 * null when it misses.
 */
function hitDistance(origin, direction, foot) {
	const ox = origin.x - foot.x;
	const oz = origin.z - foot.z;
	const a = direction.x * direction.x + direction.z * direction.z;
	if (a === 0) return null;
	const b = 2 * (ox * direction.x + oz * direction.z);
	const c = ox * ox + oz * oz - PICK_RADIUS * PICK_RADIUS;
	const discriminant = b * b - 4 * a * c;
	if (discriminant < 0) return null;
	const root = Math.sqrt(discriminant);
	for (const distance of [(-b - root) / (2 * a), (-b + root) / (2 * a)]) {
		if (distance < 0) continue;
		const y = origin.y + direction.y * distance - foot.y;
		if (y >= 0 && y <= PICK_HEIGHT) return distance;
	}
	return null;
}

/**
 * The id of the nearest resident a ray from the camera passes through, each
 * resident stood in for by an upright cylinder at their feet, or null.
 */
export function pickResident(origin, direction, positions) {
	let nearest = null;
	for (const [residentId, foot] of positions) {
		const distance = hitDistance(origin, direction, foot);
		if (distance !== null && (nearest === null || distance < nearest.distance)) nearest = { residentId, distance };
	}
	return nearest?.residentId ?? null;
}
