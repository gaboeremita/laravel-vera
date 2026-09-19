/**
 * VRMUtils.rotateVRM0() normalizes an avatar so its own front points down
 * local -Z. This converts horizontal travel into the rotation that puts that
 * front in the travel direction; it deliberately has no camera dependency.
 */
export function facingAngleForMovement(dx, dz) {
	if (dx === 0 && dz === 0) return null;

	const angle = Math.atan2(-dx, -dz);
	if (Object.is(angle, -0)) return 0;

	return angle < 0 ? angle + Math.PI * 2 : angle;
}

export function turnTowardsAngle(currentAngle, targetAngle, maxTurn) {
	const difference = Math.atan2(Math.sin(targetAngle - currentAngle), Math.cos(targetAngle - currentAngle));
	return currentAngle + Math.max(-maxTurn, Math.min(maxTurn, difference));
}

/**
 * World movement owns the avatar's position. Strip translation tracks from a
 * locomotion clip so embedded Mixamo/VRMA root motion cannot pull the model
 * backwards at the end of each cycle.
 */
export function makeClipInPlace(clip) {
	const inPlaceClip = clip.clone();
	inPlaceClip.tracks = inPlaceClip.tracks.filter((track) => !track.name.endsWith('.position'));
	return inPlaceClip;
}

/**
 * A resident may have many one-shot conversation poses. Only an explicitly
 * named walk/walking pose is eligible as its locomotion loop.
 */
export function findWalkPose(poses = []) {
	return poses.find(({ name }) => /^walking?(?:[\s_-].*)?$/iu.test(name?.trim() ?? '')) ?? null;
}
