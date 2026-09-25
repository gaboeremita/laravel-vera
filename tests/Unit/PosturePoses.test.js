import assert from 'node:assert/strict';
import { test } from 'node:test';
import { defaultPoseFor, findWorldMotionPose, isWorldMotionPose, resolvePose } from '../../resources/js/components/world/worldMotionPoses.js';

const poses = [
	{ name: 'default', posture: 'standing', animationUrl: 'stand.vrma' },
	{ name: 'default', posture: 'sitting', animationUrl: 'sit.vrma' },
	{ name: 'laugh', posture: 'standing', animationUrl: 'laugh-standing.vrma' },
	{ name: 'laugh', posture: 'sitting', animationUrl: 'laugh-sitting.vrma' },
	{ name: 'dance', posture: 'standing', animationUrl: 'dance.vrma' },
	{ name: 'stretch', posture: 'lying', animationUrl: 'stretch.vrma' },
	{ name: 'walk', posture: 'sitting', animationUrl: 'scoot.vrma' },
];

test('resolvePose plays the version for her posture', () => {
	assert.equal(resolvePose(poses, 'laugh', 'sitting'), poses[3]);
	assert.equal(resolvePose(poses, 'Laugh', 'standing'), poses[2]);
});

test('resolvePose keeps her posture when the pose has no version for it', () => {
	assert.equal(resolvePose(poses, 'dance', 'sitting'), null);
	assert.equal(resolvePose(poses, 'stretch', 'sitting'), null);
	assert.equal(resolvePose(poses, 'backflip', 'standing'), null);
});

test('resolvePose treats poses without a posture as standing', () => {
	const wave = { name: 'wave' };
	assert.equal(resolvePose([wave], 'wave', 'standing'), wave);
	assert.equal(resolvePose([wave], 'wave', 'reclining'), null);
});

test('defaultPoseFor returns the posture default or the standing default', () => {
	assert.equal(defaultPoseFor(poses, 'sitting'), poses[1]);
	assert.equal(defaultPoseFor(poses, 'lying'), poses[0]);
	assert.equal(defaultPoseFor([], 'lying'), null);
});

test('world motion poses come from standing poses only', () => {
	assert.equal(findWorldMotionPose(poses, 'walk'), null);
});

test('swim motions come from swimming poses only', () => {
	const library = [
		{ name: 'swim', posture: 'standing', animationUrl: 'wrong.vrma' },
		{ name: 'Swimming', posture: 'swimming', animationUrl: 'swim.vrma' },
		{ name: 'swimming_to_edge', posture: 'swimming', animationUrl: 'edge.vrma' },
	];
	assert.equal(findWorldMotionPose(library, 'swim'), library[1]);
	assert.equal(findWorldMotionPose(library, 'swimToEdge'), library[2]);
});

test('talking has a standing and a sitting version', () => {
	const standing = { name: 'talk', posture: 'standing' };
	const sitting = { name: 'Talking', posture: 'sitting' };
	assert.equal(findWorldMotionPose([standing, sitting], 'talk'), standing);
	assert.equal(findWorldMotionPose([standing, sitting], 'talkSitting'), sitting);
	assert.equal(findWorldMotionPose([{ name: 'talk', posture: 'lying' }], 'talkSitting'), null);
});

test('talking poses stay out of the regular pose lists', () => {
	assert.equal(isWorldMotionPose({ name: 'talk', posture: 'sitting' }), true);
	assert.equal(isWorldMotionPose({ name: 'talk', posture: 'lying' }), false);
});
