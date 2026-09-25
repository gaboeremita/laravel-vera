/**
 * Close enough to talk: this near, level with each other, and able to see
 * each other, so nobody talks across a fountain or through a wall.
 */
export const TALKING_DISTANCE = 2;

const LEVEL_TOLERANCE = 0.6;
const TALKING_EYE_HEIGHT = 1.5;

export function inTalkingReach(from, to, hasLineOfSight) {
	if (Math.abs(from.y - to.y) > LEVEL_TOLERANCE) return false;
	if (Math.hypot(to.x - from.x, to.z - from.z) > TALKING_DISTANCE) return false;
	return hasLineOfSight({ x: from.x, y: from.y + TALKING_EYE_HEIGHT, z: from.z }, { x: to.x, y: to.y + TALKING_EYE_HEIGHT, z: to.z });
}
