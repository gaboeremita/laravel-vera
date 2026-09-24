import assert from 'node:assert/strict';
import { test } from 'node:test';
import { LEAVE_WATER_DEPTH, SWIM_DEPTH } from '../../resources/js/components/world/collisionCheck.js';
import { eyeHeightFor, movementSpeed, nextMovementMode, swimEyeY, targetFov } from '../../resources/js/components/world/playerMotion.js';

const dry = { runHeld: false, crouchToggled: false, waterDepth: 0 };

test('the user starts swimming above the swim depth and stops below the leave depth', () => {
	assert.equal(nextMovementMode({ ...dry, current: 'walking', waterDepth: SWIM_DEPTH + 0.01 }), 'swimming');
	assert.equal(nextMovementMode({ ...dry, current: 'walking', waterDepth: 1.0 }), 'walking');
	assert.equal(nextMovementMode({ ...dry, current: 'swimming', waterDepth: 1.0 }), 'swimming');
	assert.equal(nextMovementMode({ ...dry, current: 'swimming', waterDepth: LEAVE_WATER_DEPTH - 0.01 }), 'walking');
});

test('crouching ends in deep water and cannot start while swimming', () => {
	assert.equal(nextMovementMode({ ...dry, current: 'crouching', crouchToggled: true, waterDepth: 1.2 }), 'swimming');
	assert.equal(nextMovementMode({ ...dry, current: 'swimming', crouchToggled: true, waterDepth: 1.0 }), 'swimming');
});

test('holding the run key while crouched runs', () => {
	assert.equal(nextMovementMode({ ...dry, current: 'crouching', crouchToggled: true, runHeld: true }), 'running');
	assert.equal(nextMovementMode({ ...dry, current: 'walking', crouchToggled: true }), 'crouching');
});

test('the run key only runs while moving', () => {
	assert.equal(nextMovementMode({ ...dry, current: 'walking', runHeld: true, moving: true }), 'running');
	assert.equal(nextMovementMode({ ...dry, current: 'walking', runHeld: true, moving: false }), 'walking');
});

test('each mode moves at its own speed', () => {
	assert.equal(movementSpeed('walking', false), 3.5);
	assert.equal(movementSpeed('running', true), 7);
	assert.equal(movementSpeed('crouching', false), 1.75);
	assert.ok(Math.abs(movementSpeed('swimming', false) - 1.925) < 1e-9);
	assert.ok(Math.abs(movementSpeed('swimming', true) - 2.975) < 1e-9);
});

test('crouching lowers the eye height', () => {
	assert.equal(eyeHeightFor('walking'), 1.6);
	assert.equal(eyeHeightFor('crouching'), 1.05);
});

test('the swimming view floats just above the surface and bobs gently', () => {
	for (const t of [0, 0.3, 0.5, 1.1, 2.7]) {
		const y = swimEyeY(10, t, false);
		assert.ok(y >= 10.1 - 1e-9 && y <= 10.14 + 1e-9);
	}
	assert.equal(swimEyeY(10, 0.5, true), 10.12);
	assert.equal(swimEyeY(10, 1.3, true), 10.12);
});

test('running widens the field of view unless motion is reduced', () => {
	assert.equal(targetFov('running', false), 76);
	assert.equal(targetFov('running', true), 70);
	assert.equal(targetFov('walking', false), 70);
});
