import assert from 'node:assert/strict';
import { test } from 'node:test';
import { LEAVE_WATER_DEPTH, SWIM_DEPTH } from '../../resources/js/components/world/collisionCheck.js';
import { GRAVITY, JUMP_HEIGHT, canJump, eyeHeightFor, jumpVelocity, landingDip, movementSpeed, nextMovementMode, swimEyeY, targetFov } from '../../resources/js/components/world/playerMotion.js';

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

test('holding the run key while crouched stays crouched', () => {
	assert.equal(nextMovementMode({ ...dry, current: 'crouching', crouchToggled: true, runHeld: true }), 'crouching');
	assert.equal(nextMovementMode({ ...dry, current: 'walking', crouchToggled: true }), 'crouching');
});

test('the run key only runs while moving and standing', () => {
	assert.equal(nextMovementMode({ ...dry, current: 'swimming', runHeld: true, waterDepth: 1.2 }), 'swimming');
	assert.equal(nextMovementMode({ ...dry, current: 'walking', runHeld: true, moving: true }), 'running');
	assert.equal(nextMovementMode({ ...dry, current: 'walking', runHeld: true, moving: false }), 'walking');
});

test('each mode moves at its own speed', () => {
	assert.equal(movementSpeed('walking'), 3.5);
	assert.equal(movementSpeed('running'), 7);
	assert.equal(movementSpeed('crouching'), 1.75);
	assert.ok(Math.abs(movementSpeed('swimming') - 1.925) < 1e-9);
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

test('a jump peaks at the jump height', () => {
	const velocity = jumpVelocity();
	assert.ok(Math.abs((velocity * velocity) / (2 * GRAVITY) - JUMP_HEIGHT) < 1e-9);
	assert.ok(JUMP_HEIGHT >= 1 && JUMP_HEIGHT <= 1.2);
});

test('the user can jump only on their feet and out of the water', () => {
	assert.equal(canJump({ mode: 'walking', seated: false, airborne: false }), true);
	assert.equal(canJump({ mode: 'crouching', seated: false, airborne: false }), true);
	assert.equal(canJump({ mode: 'swimming', seated: false, airborne: false }), false);
	assert.equal(canJump({ mode: 'walking', seated: true, airborne: false }), false);
	assert.equal(canJump({ mode: 'walking', seated: false, airborne: true }), false);
});

test('landing dips the view more after a longer fall, up to a limit', () => {
	assert.ok(landingDip(-8, false) > landingDip(-3, false));
	assert.ok(landingDip(-100, false) <= 0.14);
	assert.equal(landingDip(-8, true), 0);
});
