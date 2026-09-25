import assert from 'node:assert/strict';
import test from 'node:test';
import { facingAngleForMovement, fadesIdleForPose, headingToward, idleWait, makeClipInPlace, shouldFaceUser, speakingSeconds, turnTowardsAngle } from '../../resources/js/components/world/residentMotion.js';
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

test('a resident turns to the user only when asked, while talking and still', () => {
	assert.equal(shouldFaceUser({ requested: true, inConversation: true }), true);
	assert.equal(shouldFaceUser({ requested: false, inConversation: true }), false);
	assert.equal(shouldFaceUser({ requested: true, inConversation: false }), false);
	assert.equal(shouldFaceUser({ requested: true, inConversation: true, routing: true }), false);
	assert.equal(shouldFaceUser({ requested: true, inConversation: true, placing: true }), false);
	assert.equal(shouldFaceUser({ requested: true, inConversation: true, restingOnSpot: true }), false);
	assert.equal(shouldFaceUser({ requested: true, inConversation: true, wandering: true }), false);
});

test('the heading toward the user matches the heading for moving toward them', () => {
	const from = { x: 1, z: 2 };
	const to = { x: -3, z: 5 };
	assert.equal(headingToward(from, to), facingAngleForMovement(to.x - from.x, to.z - from.z));
});

test('saying a line without a voice takes time by its length, within limits', () => {
	assert.equal(speakingSeconds('one two three four five'), 2);
	assert.equal(speakingSeconds('Hi.'), 1.5);
	assert.equal(speakingSeconds('word '.repeat(200)), 20);
	assert.equal(speakingSeconds('   '), 0);
});

test('a one-off pose fades out whatever clip she idles in instead of averaging with it', () => {
	const standingIdle = { name: 'idle' };
	const talk = { name: 'talk' };
	const wave = { name: 'wave' };
	assert.equal(fadesIdleForPose({ idle: talk, pose: wave }), true);
	assert.equal(fadesIdleForPose({ idle: standingIdle, pose: wave }), true);
	assert.equal(fadesIdleForPose({ idle: wave, pose: wave }), false);
	assert.equal(fadesIdleForPose({ idle: null, pose: wave }), false);
});

test('she settles in and decides half as often while resting on something', () => {
	const middle = () => 0.5;
	assert.equal(idleWait('standing', middle), 20000);
	assert.equal(idleWait('sitting', middle), 40000);
	assert.equal(idleWait('reclining', middle), 40000);
	assert.equal(idleWait('lying', middle), 40000);
	assert.equal(idleWait('swimming', middle), 20000);
});
