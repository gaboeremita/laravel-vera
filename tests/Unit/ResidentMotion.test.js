import assert from 'node:assert/strict';
import test from 'node:test';
import { facingAngleForMovement, makeClipInPlace, turnTowardsAngle } from '../../resources/js/components/world/residentMotion.js';
import { findWorldMotionPose } from '../../resources/js/components/world/worldMotionPoses.js';

test('faces the avatar front in its direction of travel', () => {
	assert.equal(facingAngleForMovement(0, -1), 0);
	assert.equal(facingAngleForMovement(0, 1), Math.PI);
	assert.equal(facingAngleForMovement(0, 0), null);
});

test('turns through the shortest direction without involving the camera', () => {
	assert.ok(turnTowardsAngle(Math.PI - 0.1, -Math.PI + 0.1, 0.5) > Math.PI);
});

test('selects only an explicitly named walk pose', () => {
	const walkPose = { name: 'Walking', animationUrl: '/walk.vrma' };
	assert.equal(findWorldMotionPose([{ name: 'wave' }, walkPose], 'walk'), walkPose);
	assert.equal(findWorldMotionPose([{ name: 'moonwalk' }], 'walk'), null);
});

test('removes root-motion tracks from a walk clip', () => {
	const clip = {
		tracks: [{ name: 'hips.position' }, { name: 'hips.quaternion' }],
		clone() { return { ...this, tracks: [...this.tracks] }; },
	};

	assert.deepEqual(makeClipInPlace(clip).tracks, [{ name: 'hips.quaternion' }]);
});
