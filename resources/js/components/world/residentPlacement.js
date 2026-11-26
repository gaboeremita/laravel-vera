/**
 * Where a resident stands when the creator points a camera ray at the world:
 * the ground under the first surface it meets, moved to the nearest spot a
 * body fits and can walk on from. Null when the ray meets nothing or there is
 * no such spot. While the navigation grid is still building, only the body fit
 * is checked.
 */
export function placementAlong(ray, collisionWorld, navigation) {
	const hit = collisionWorld.octree.rayIntersect(ray);
	if (!hit) return null;
	const ground = collisionWorld.groundBelow(hit.position);
	if (navigation?.isComplete) return navigation.standingPointNear(ground);
	return collisionWorld.isBodyStuck(ground) ? null : ground;
}
