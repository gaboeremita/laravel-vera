const MARGIN = 0.3;

// Keeps a point comfortably inside the room's modeled extent rather than
// exactly on its edge. Shared between the player and residents — the room's
// actual GLB (confirmed via the "no collision meshes found" console warning
// in WorldEnvironment.jsx) has no wall/floor collision geometry authored
// into it at all, so this bounding-box clamp is the only thing standing
// between either of them and wandering into empty space outside the room.
export function clampToBounds(x, y, z, bounds) {
	if (!bounds || bounds.isEmpty()) return { x, y, z };
	return {
		x: Math.min(Math.max(x, bounds.min.x + MARGIN), bounds.max.x - MARGIN),
		y: Math.min(Math.max(y, bounds.min.y), bounds.max.y),
		z: Math.min(Math.max(z, bounds.min.z + MARGIN), bounds.max.z - MARGIN),
	};
}
