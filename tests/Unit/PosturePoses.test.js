import assert from 'node:assert/strict';
import { test } from 'node:test';
import { defaultPoseFor, findWorldMotionPose, resolvePose } from '../../resources/js/components/world/worldMotionPoses.js';

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
	assert.deepEqual(resolvePose(poses, 'laugh', 'sitting'), { pose: poses[3], standUp: false });
	assert.deepEqual(resolvePose(poses, 'Laugh', 'standing'), { pose: poses[2], standUp: false });
});

test('resolvePose falls back to the standing version and makes her stand up', () => {
	assert.deepEqual(resolvePose(poses, 'dance', 'sitting'), { pose: poses[4], standUp: true });
});

test('resolvePose returns null for a pose with neither version', () => {
	assert.equal(resolvePose(poses, 'stretch', 'sitting'), null);
	assert.equal(resolvePose(poses, 'backflip', 'standing'), null);
});

test('resolvePose treats poses without a posture as standing', () => {
	assert.deepEqual(resolvePose([{ name: 'wave' }], 'wave', 'reclining'), { pose: { name: 'wave' }, standUp: true });
});

test('defaultPoseFor returns the posture default or the standing default', () => {
	assert.equal(defaultPoseFor(poses, 'sitting'), poses[1]);
	assert.equal(defaultPoseFor(poses, 'lying'), poses[0]);
	assert.equal(defaultPoseFor([], 'lying'), null);
});

test('world motion poses come from standing poses only', () => {
	assert.equal(findWorldMotionPose(poses, 'walk'), null);
});
