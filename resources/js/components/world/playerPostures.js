import { STACK_HEIGHT } from './spotOccupancy.js';

const DEGREE = Math.PI / 180;
export const MAX_PITCH = 1.35;
const UPPER_TIER_TOWARD_FEET = 0.25;

const POSTURE_VIEWS = {
	sitting: { height: 0.72, back: 0.1, pitch: 0, yawRange: 75 * DEGREE, pitchMin: -60 * DEGREE, pitchMax: 50 * DEGREE },
	reclining: { height: 0.55, back: 0.45, pitch: 20 * DEGREE, yawRange: 55 * DEGREE, pitchMin: -35 * DEGREE, pitchMax: 55 * DEGREE },
	lying: { height: 0.28, back: 0.7, pitch: 55 * DEGREE, yawRange: 45 * DEGREE, pitchMin: -10 * DEGREE, pitchMax: 80 * DEGREE },
};

export const RESTING_POSTURES = Object.keys(POSTURE_VIEWS);

/** The camera yaw that looks along a horizontal direction. */
export function yawForDirection(dx, dz) {
	return Math.atan2(-dx, -dz);
}

/**
 * Where the user's eyes go on a spot: the spot marks the hips and its facing
 * points toward the feet or the desk, so the head sits behind it. Each tier
 * up a shared spot lies one body higher and a little toward the feet, turned
 * toward the head and looking down, and from on top the user can look all the
 * way around.
 */
export function postureView({ spot, posture, tier = 0 }) {
	const shape = POSTURE_VIEWS[posture];
	const dx = Math.sin(spot.facing);
	const dz = Math.cos(spot.facing);
	const back = tier > 0 ? shape.back - UPPER_TIER_TOWARD_FEET : shape.back;
	return {
		eye: {
			x: spot.position.x - dx * back,
			y: spot.position.y + shape.height + tier * STACK_HEIGHT,
			z: spot.position.z - dz * back,
		},
		yaw: yawForDirection(dx, dz) + (tier > 0 ? Math.PI : 0),
		pitch: tier > 0 ? -MAX_PITCH : shape.pitch,
		yawRange: tier > 0 ? Math.PI : shape.yawRange,
		pitchMin: tier > 0 ? -MAX_PITCH : shape.pitchMin,
		pitchMax: tier > 0 ? MAX_PITCH : shape.pitchMax,
	};
}

export function clampLook({ yaw, pitch }, view) {
	const offset = Math.atan2(Math.sin(yaw - view.yaw), Math.cos(yaw - view.yaw));
	return {
		yaw: view.yaw + Math.max(-view.yawRange, Math.min(view.yawRange, offset)),
		pitch: Math.max(view.pitchMin, Math.min(view.pitchMax, pitch)),
	};
}
