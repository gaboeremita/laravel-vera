import assert from 'node:assert/strict';
import test from 'node:test';
import { facingAngleForMovement, headingToward, makeClipInPlace, shouldFaceUser, turnTowardsAngle } from '../../resources/js/components/world/residentMotion.js';
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

test('a resident faces the user only while talking and still', () => {
	assert.equal(shouldFaceUser({ inConversation: true }), true);
	assert.equal(shouldFaceUser({ inConversation: false }), false);
	assert.equal(shouldFaceUser({ inConversation: true, routing: true }), false);
	assert.equal(shouldFaceUser({ inConversation: true, placing: true }), false);
	assert.equal(shouldFaceUser({ inConversation: true, restingOnSpot: true }), false);
	assert.equal(shouldFaceUser({ inConversation: true, wandering: true }), false);
});

test('the heading toward the user matches the heading for moving toward them', () => {
	const from = { x: 1, z: 2 };
	const to = { x: -3, z: 5 };
	assert.equal(headingToward(from, to), facingAngleForMovement(to.x - from.x, to.z - from.z));
});
