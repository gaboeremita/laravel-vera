import assert from 'node:assert/strict';
import { test } from 'node:test';
import { MAX_PITCH, clampLook, postureView } from '../../resources/js/components/world/playerPostures.js';
import { STACK_HEIGHT } from '../../resources/js/components/world/spotOccupancy.js';

const DEGREE = Math.PI / 180;
const close = (actual, expected) => assert.ok(Math.abs(actual - expected) < 1e-9, `${actual} ≉ ${expected}`);
const spot = { position: { x: 2, y: 0.5, z: 3 }, facing: 0 };

test('each resting posture places the eye above and behind the spot', () => {
	for (const [posture, height, back] of [['sitting', 0.72, 0.1], ['reclining', 0.55, 0.45], ['lying', 0.28, 0.7]]) {
		const view = postureView({ spot, posture });
		close(view.eye.y, 0.5 + height);
		close(view.eye.x, 2);
		close(view.eye.z, 3 - back);
	}
});

test('the upper tier of a shared spot lies one body higher, toward the feet, looking down', () => {
	const lower = postureView({ spot, posture: 'lying' });
	const upper = postureView({ spot, posture: 'lying', tier: 1 });
	close(upper.eye.y, lower.eye.y + STACK_HEIGHT);
	close(upper.eye.x, lower.eye.x);
	close(upper.eye.z, lower.eye.z + 0.25);
	close(upper.pitch, -MAX_PITCH);
	close(Math.cos(upper.yaw - lower.yaw), -1);
	close(lower.pitch, 55 * DEGREE);
});

test('from the upper tier the user can look all the way around and down', () => {
	const upper = postureView({ spot, posture: 'lying', tier: 1 });
	const behind = clampLook({ yaw: upper.yaw + Math.PI, pitch: -MAX_PITCH }, upper);
	close(Math.cos(behind.yaw - upper.yaw), -1);
	close(behind.pitch, -MAX_PITCH);
	const lower = postureView({ spot, posture: 'lying' });
	close(lower.yawRange, 45 * DEGREE);
	close(lower.pitchMin, -10 * DEGREE);
	close(lower.pitchMax, 80 * DEGREE);
});

test('the eye sits opposite the spot facing', () => {
	const view = postureView({ spot: { ...spot, facing: Math.PI / 2 }, posture: 'reclining' });
	close(view.eye.x, 2 - 0.45);
	close(view.eye.z, 3);
});

test('each posture has its base pitch and look limits', () => {
	const sitting = postureView({ spot, posture: 'sitting' });
	close(sitting.pitch, 0);
	close(sitting.yawRange, 75 * DEGREE);
	close(sitting.pitchMin, -60 * DEGREE);
	close(sitting.pitchMax, 50 * DEGREE);
	const reclining = postureView({ spot, posture: 'reclining' });
	close(reclining.pitch, 20 * DEGREE);
	close(reclining.yawRange, 55 * DEGREE);
	const lying = postureView({ spot, posture: 'lying' });
	close(lying.pitch, 55 * DEGREE);
	close(lying.yawRange, 45 * DEGREE);
	close(lying.pitchMax, 80 * DEGREE);
});

test('the view looks the way the spot faces', () => {
	const view = postureView({ spot, posture: 'sitting' });
	close(Math.sin(view.yaw), 0);
	close(Math.cos(view.yaw), -1);
});

test('clampLook keeps the look within range around the spot facing', () => {
	const view = { yaw: 0, yawRange: 45 * DEGREE, pitchMin: -10 * DEGREE, pitchMax: 30 * DEGREE };
	close(clampLook({ yaw: 90 * DEGREE, pitch: 0 }, view).yaw, 45 * DEGREE);
	close(clampLook({ yaw: -90 * DEGREE, pitch: 0 }, view).yaw, -45 * DEGREE);
	close(clampLook({ yaw: 0, pitch: 60 * DEGREE }, view).pitch, 30 * DEGREE);
	close(clampLook({ yaw: 0, pitch: -60 * DEGREE }, view).pitch, -10 * DEGREE);
});

test('clampLook handles facings across the ±π wrap', () => {
	const view = { yaw: Math.PI - 0.1, yawRange: 0.5, pitchMin: -1, pitchMax: 1 };
	const inside = clampLook({ yaw: -Math.PI + 0.1, pitch: 0 }, view);
	close(Math.cos(inside.yaw - (Math.PI + 0.1)), 1);
	const outside = clampLook({ yaw: -Math.PI + 1, pitch: 0 }, view);
	close(outside.yaw, Math.PI - 0.1 + 0.5);
});
