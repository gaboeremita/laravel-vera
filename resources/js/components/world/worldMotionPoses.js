export const WORLD_MOTION_POSES = [
	{ key: 'walkStart', name: 'walk-start', label: 'Walk Start', description: 'Plays once before the resident begins moving.' },
	{ key: 'walk', name: 'walk', label: 'Walk', description: 'Loops while the resident walks forward.' },
	{ key: 'walkStop', name: 'walk-stop', label: 'Walk Stop', description: 'Plays once when the resident comes to a stop.' },
	{ key: 'greeting', name: 'greeting', label: 'Greeting', description: 'Plays once when the player starts a conversation.' },
];

const MOTION_POSE_NAMES = {
	walkStart: ['walk-start', 'walk_start', 'walk start'],
	walk: ['walk', 'walking', 'walk-cycle', 'walk_cycle', 'walk cycle'],
	walkStop: ['walk-stop', 'walk_stop', 'walk stop'],
	greeting: ['greeting', 'greet'],
};

export function findWorldMotionPose(poses = [], key) {
	const names = MOTION_POSE_NAMES[key] ?? [];
	return poses.find(({ name }) => names.includes(name?.trim().toLowerCase())) ?? null;
}

export function isWorldMotionPose(pose) {
	return WORLD_MOTION_POSES.some(({ key }) => findWorldMotionPose([pose], key));
}
