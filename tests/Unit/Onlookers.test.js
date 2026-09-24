import assert from 'node:assert/strict';
import { test } from 'node:test';
import { selectOnlookers } from '../../resources/js/components/world/onlookers.js';

const userEye = { x: 0, y: 1.6, z: 0 };
const facingUser = (x, z) => Math.atan2(x, z);
const facingAway = (x, z) => Math.atan2(-x, -z);

function onlookers(poses, { blocked = [] } = {}) {
	const residents = Object.keys(poses).map((id) => ({ id }));
	return selectOnlookers({
		residents,
		userEye,
		userFloorId: 'ground',
		floorOf: (y) => (y < 4 ? 'ground' : 'upper'),
		residentPose: (id) => poses[id],
		hasLineOfSight: (from) => !blocked.some((point) => point.x === from.x && point.z === from.z),
	}).map((resident) => resident.id);
}

test('a resident across the room looking at the user sees them', () => {
	assert.deepEqual(onlookers({ vera: { position: { x: 10, y: 0, z: 0 }, yaw: facingUser(10, 0) } }), ['vera']);
});

test('a resident across the room looking away does not', () => {
	assert.deepEqual(onlookers({ vera: { position: { x: 10, y: 0, z: 0 }, yaw: facingAway(10, 0) } }), []);
});

test('a resident close by notices whichever way she faces', () => {
	assert.deepEqual(onlookers({ vera: { position: { x: 3, y: 0, z: 0 }, yaw: facingAway(3, 0) } }), ['vera']);
});

test('residents too far, upstairs or behind a wall do not see the user', () => {
	assert.deepEqual(onlookers({
		far: { position: { x: 16, y: 0, z: 0 }, yaw: facingUser(16, 0) },
		upstairs: { position: { x: 2, y: 5, z: 0 }, yaw: facingUser(2, 0) },
		walled: { position: { x: 0, y: 0, z: 8 }, yaw: facingUser(0, 8) },
	}, { blocked: [{ x: 0, z: 8 }] }), []);
});

test('residents without a known pose are skipped', () => {
	assert.deepEqual(onlookers({ ghost: null }), []);
});
