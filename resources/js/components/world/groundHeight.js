import { Raycaster, Vector3 } from 'three';

// Fallback only for when the room's own ceiling height isn't known yet.
const DEFAULT_RAY_ORIGIN_HEIGHT = 50;
// Keeps the ray's start point just under a roof/ceiling rather than
// touching it.
const CEILING_MARGIN = 0.1;
const rayOrigin = new Vector3();
const rayDirection = new Vector3(0, -1, 0);
const raycaster = new Raycaster();

// Both the player and residents were using a fixed, guessed Y coordinate
// (camera always at eye height 1.6, a resident at whatever Y its position
// field held) instead of ever checking the actual floor. If a room's real
// floor isn't at world Y=0, everything floats or sinks relative to it —
// there was no grounding at all. This casts straight down from above the
// given point and returns the height of whatever the ray actually hits,
// so callers can rest an entity on the real surface instead of assuming one.
//
// originY should be the room's own ceiling height (worldBounds.max.y) when
// known — casting from a fixed sky-high altitude instead hits a roof's
// *outside* top surface before the ray can ever reach the interior floor
// beneath it, grounding an entity on the roof. Starting just under the
// actual ceiling means there's nothing above the room left to hit.
export function getGroundHeight(x, z, scene, fallback = 0, originY = DEFAULT_RAY_ORIGIN_HEIGHT) {
	if (!scene) return fallback;
	rayOrigin.set(x, originY - CEILING_MARGIN, z);
	raycaster.set(rayOrigin, rayDirection);
	const hits = raycaster.intersectObject(scene, true);
	return hits.length > 0 ? hits[0].point.y : fallback;
}

// The originY to pass into getGroundHeight, derived from a worldBounds
// ref — undefined (falls back to the default sky-high origin) until the
// room's own extent is actually known.
export function ceilingHeight(bounds) {
	return bounds && !bounds.isEmpty() ? bounds.max.y : undefined;
}
