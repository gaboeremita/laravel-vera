export const POSTURES = [
	{ key: 'standing', label: 'Standing' },
	{ key: 'sitting', label: 'Sitting' },
	{ key: 'lying', label: 'Lying' },
	{ key: 'reclining', label: 'Reclining' },
	{ key: 'swimming', label: 'Swimming' },
];

export const WORLD_MOTION_POSES = [
	{ key: 'walkStart', name: 'walk-start', posture: 'standing', label: 'Walk Start', description: 'Plays once before the resident begins moving.' },
	{ key: 'walk', name: 'walk', posture: 'standing', label: 'Walk', description: 'Loops while the resident walks forward.' },
	{ key: 'walkStop', name: 'walk-stop', posture: 'standing', label: 'Walk Stop', description: 'Plays once when the resident comes to a stop.' },
	{ key: 'greeting', name: 'greeting', posture: 'standing', label: 'Greeting', description: 'Plays once when the player starts a conversation.' },
	{ key: 'swim', name: 'swim', posture: 'swimming', label: 'Swim', description: 'Loops while the resident swims through deep water.' },
	{ key: 'swimToEdge', name: 'swim-to-edge', posture: 'swimming', label: 'Swim To Edge', description: 'Plays once when the resident stops at the side of the pool, and holds while she rests there.' },
];

const MOTION_POSE_NAMES = {
	walkStart: ['walk-start', 'walk_start', 'walk start'],
	walk: ['walk', 'walking', 'walk-cycle', 'walk_cycle', 'walk cycle'],
	walkStop: ['walk-stop', 'walk_stop', 'walk stop'],
	greeting: ['greeting', 'greet'],
	swim: ['swim', 'swimming'],
	swimToEdge: ['swim-to-edge', 'swim_to_edge', 'swim to edge', 'swimming-to-edge', 'swimming_to_edge', 'swimming to edge'],
};

const postureOf = (pose) => pose?.posture ?? 'standing';
const sameName = (a, b) => a?.trim().toLowerCase() === b?.trim().toLowerCase();

export function findWorldMotionPose(poses = [], key) {
	const names = MOTION_POSE_NAMES[key] ?? [];
	const posture = WORLD_MOTION_POSES.find((definition) => definition.key === key)?.posture ?? 'standing';
	return poses.find((pose) => postureOf(pose) === posture && names.includes(pose.name?.trim().toLowerCase())) ?? null;
}

export function isWorldMotionPose(pose) {
	return WORLD_MOTION_POSES.some(({ key }) => findWorldMotionPose([pose], key));
}

/**
 * The version of a pose to play in a posture. With no version for that
 * posture, the standing version plays and she has to stand up first.
 *
 * @returns {{ pose: object, standUp: boolean } | null}
 */
export function resolvePose(poses = [], name, posture = 'standing') {
	const inPosture = poses.find((pose) => postureOf(pose) === posture && sameName(pose.name, name));
	if (inPosture) return { pose: inPosture, standUp: false };

	const standing = poses.find((pose) => postureOf(pose) === 'standing' && sameName(pose.name, name));
	return standing ? { pose: standing, standUp: posture !== 'standing' } : null;
}

/**
 * The pose she holds while resting in a posture; a posture without its own
 * default falls back to the standing default.
 */
export function defaultPoseFor(poses = [], posture = 'standing') {
	return poses.find((pose) => postureOf(pose) === posture && sameName(pose.name, 'default'))
		?? poses.find((pose) => postureOf(pose) === 'standing' && sameName(pose.name, 'default'))
		?? null;
}
