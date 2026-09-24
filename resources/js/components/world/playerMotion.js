import { LEAVE_WATER_DEPTH, SWIM_DEPTH } from './collisionCheck.js';

export const WALK_SPEED = 3.5;
export const STANDING_EYE_HEIGHT = 1.6;
export const CROUCHING_EYE_HEIGHT = 1.05;
const SWIM_EYE_ABOVE_SURFACE = 0.12;
const SWIM_BOB_AMPLITUDE = 0.02;
const SWIM_BOB_HZ = 0.5;
const WALKING_FOV = 70;
const RUNNING_FOV = 76;

const SPEED_FACTORS = { walking: 1, running: 2, crouching: 0.5, swimming: 0.55 };
const SWIM_RUN_FACTOR = 0.85;

export function nextMovementMode({ current, runHeld, crouchToggled, waterDepth, moving = true }) {
	const inWater = current === 'swimming' ? waterDepth > LEAVE_WATER_DEPTH : waterDepth > SWIM_DEPTH;
	if (inWater) return 'swimming';
	if (crouchToggled && !runHeld && waterDepth <= LEAVE_WATER_DEPTH) return 'crouching';
	return runHeld && moving ? 'running' : 'walking';
}

export function movementSpeed(mode, runHeld) {
	if (mode === 'swimming' && runHeld) return WALK_SPEED * SWIM_RUN_FACTOR;
	return WALK_SPEED * (SPEED_FACTORS[mode] ?? 1);
}

export function eyeHeightFor(mode) {
	return mode === 'crouching' ? CROUCHING_EYE_HEIGHT : STANDING_EYE_HEIGHT;
}

export function swimEyeY(surfaceY, timeSeconds, reducedMotion) {
	const bob = reducedMotion ? 0 : SWIM_BOB_AMPLITUDE * Math.sin(2 * Math.PI * SWIM_BOB_HZ * timeSeconds);
	return surfaceY + SWIM_EYE_ABOVE_SURFACE + bob;
}

export function targetFov(mode, reducedMotion) {
	return mode === 'running' && !reducedMotion ? RUNNING_FOV : WALKING_FOV;
}
