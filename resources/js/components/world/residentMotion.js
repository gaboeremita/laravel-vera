/**
 * The turn that makes a VRM model face -Z inside her body: VRM 0.x files
 * already face -Z, VRM 1.0 files face +Z.
 */
export function modelYaw(metaVersion) {
	return metaVersion === '0' ? 0 : Math.PI;
}

/**
 * A resident's body faces local -Z once modelYaw() has turned her model. This
 * converts horizontal travel into the rotation that puts that front in the
 * travel direction; it deliberately has no camera dependency.
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

/**
 * A resident turns to the user when asked to (as a conversation opens, and
 * as she strikes a pose in it), once she is still: not walking a route, not
 * being placed, and not holding a seat, bed or lounger, whose direction she
 * keeps.
 */
export function shouldFaceUser({ requested, inConversation, routing = false, placing = false, restingOnSpot = false, wandering = false }) {
	return requested && inConversation && !routing && !placing && !restingOnSpot && !wandering;
}

export function headingToward(from, to) {
	return facingAngleForMovement(to.x - from.x, to.z - from.z);
}

const SPOKEN_WORDS_PER_SECOND = 2.5;
const MIN_SPEAKING_SECONDS = 1.5;
const MAX_SPEAKING_SECONDS = 20;

/** How long saying a line takes when there is no voice to time it by. */
export function speakingSeconds(text) {
	const words = (text ?? '').trim().split(/\s+/).filter(Boolean).length;
	if (words === 0) return 0;
	return Math.min(MAX_SPEAKING_SECONDS, Math.max(MIN_SPEAKING_SECONDS, words / SPOKEN_WORDS_PER_SECOND));
}

/**
 * Whether a one-off pose fades out the clip she idles in while it plays, so
 * the two never average and the pose shows at full strength.
 */
export function fadesIdleForPose({ idle, pose }) {
	return Boolean(idle) && idle !== pose;
}

const MIN_IDLE_MS = 10000;
const MAX_IDLE_MS = 30000;
const RESTING_POSTURES = ['sitting', 'reclining', 'lying'];

/**
 * How long she waits before deciding her next step; resting on something she
 * settles in, so she decides half as often. A resident with her own pace,
 * { min, max } in seconds, waits within it wherever she is.
 */
export function idleWait(posture = 'standing', random = Math.random, pace = null) {
	if (pace) return (pace.min + random() * (pace.max - pace.min)) * 1000;
	const wait = MIN_IDLE_MS + random() * (MAX_IDLE_MS - MIN_IDLE_MS);
	return RESTING_POSTURES.includes(posture) ? wait * 2 : wait;
}
