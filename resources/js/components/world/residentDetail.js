export const LOAD_DISTANCE = 30;
export const UNLOAD_DISTANCE = 45;
const SPRING_BONE_DISTANCE = 10;
const HALF_RATE_DISTANCE = 20;

/** Residents who move or decide on their own stay loaded wherever the user is, so they keep living their lives out of sight. */
export function staysLoaded(behavior) {
	return behavior !== 'stationary';
}

export function shouldLoad({ behavior, distance }) {
	return staysLoaded(behavior) || distance <= LOAD_DISTANCE;
}

export function shouldUnload({ behavior, distance }) {
	return !staysLoaded(behavior) && distance > UNLOAD_DISTANCE;
}

/**
 * How much of a resident's body is worth animating this frame: nothing when
 * she is off screen or far away, hair and cloth physics only up close, and
 * half the frame rate at a distance.
 */
export function visualDetail({ distance, visible }) {
	if (!visible || distance >= LOAD_DISTANCE) return { animate: false, springBones: false, frameStride: 1 };
	return { animate: true, springBones: distance < SPRING_BONE_DISTANCE, frameStride: distance < HALF_RATE_DISTANCE ? 1 : 2 };
}

/** VRM.update, with the spring bones left out when they are not worth their cost. */
export function updateVrm(vrm, delta, springBones) {
	vrm.humanoid?.update();
	vrm.lookAt?.update(delta);
	vrm.expressionManager?.update();
	vrm.nodeConstraintManager?.update();
	if (springBones) vrm.springBoneManager?.update(delta);
	vrm.materials?.forEach((material) => material.update?.(delta));
}

/** Counts for the performance overlay from each loaded resident's latest detail level. */
export function summarizeResidents(details) {
	let loaded = 0;
	let animated = 0;
	let springBones = 0;
	for (const detail of details.values()) {
		loaded += 1;
		if (detail.animate) animated += 1;
		if (detail.springBones) springBones += 1;
	}
	return { loaded, animated, springBones };
}

const LOD_NEAR = 15;
const LOD_FAR = 18;

/** Whether she should wear her low-detail model: beyond 18 m, back to the full one inside 15 m, so she does not flicker on the line. */
export function wantsLod({ hasLod, distance, wearingLod }) {
	if (!hasLod) return false;
	return wearingLod ? distance > LOD_NEAR : distance > LOD_FAR;
}

/** Beyond this distance from the user a resident keeps living at a slower pace. */
export const FAR_DISTANCE = 30;
const FAR_PACE = { min: 60, max: 120 };
const FAR_TURN_GAP_MS = 30000;

export function isFar(point, user) {
	if (!point || !user) return false;
	return Math.hypot(point.x - user.x, point.y - user.y, point.z - user.z) > FAR_DISTANCE;
}

/** Her decision pace in seconds: her own (or the usual one) up close, one to two minutes far away. */
export function decisionPace(ownPace, far) {
	return far ? FAR_PACE : ownPace;
}

/** The pause after a line of a conversation: the usual gap, or 30 s when everyone in it is far from the user. */
export function turnGap(usualMs, farAway) {
	return farAway ? FAR_TURN_GAP_MS : usualMs;
}
