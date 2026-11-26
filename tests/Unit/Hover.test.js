import assert from 'node:assert/strict';
import { test } from 'node:test';
import { AnimationClip, Quaternion, QuaternionKeyframeTrack, VectorKeyframeTrack } from 'three';
import { HOVER_BOB_HEIGHT, HOVER_BOB_SECONDS, HOVER_HEIGHT, hoverOffset, withHeldLegs } from '../../resources/js/utils/hover.js';

const vrm = {
	humanoid: {
		getNormalizedBoneNode: (bone) => ({ name: `Normalized_${bone}` }),
	},
};

const quaternion = (node) => new QuaternionKeyframeTrack(`${node}.quaternion`, [0], [0, 0, 0, 1]);

test('a hovering clip keeps every track but the legs, which hold their modelled pose', () => {
	const clip = new AnimationClip('wave', 2, [
		new VectorKeyframeTrack('Normalized_hips.position', [0], [0, 1, 0]),
		quaternion('Normalized_hips'),
		quaternion('Normalized_spine'),
		quaternion('Normalized_rightUpperArm'),
		quaternion('Normalized_leftUpperLeg'),
		quaternion('Normalized_rightLowerLeg'),
		quaternion('Normalized_leftFoot'),
		quaternion('Normalized_rightToes'),
	]);
	const bent = new Quaternion(0.3, 0, 0, 0.954);

	const hovering = withHeldLegs(clip, vrm, new Map([['leftUpperLeg', bent]]));

	assert.deepEqual(hovering.tracks.map((track) => track.name), ['Normalized_hips.position', 'Normalized_hips.quaternion', 'Normalized_spine.quaternion', 'Normalized_rightUpperArm.quaternion', 'Normalized_leftUpperLeg.quaternion']);
	assert.deepEqual([...hovering.tracks[4].values], [...new Float32Array(bent.toArray())]);
	assert.equal(hovering.duration, 2);
	assert.equal(clip.tracks.length, 8);
});

test('a hovering body bobs around its hover height', () => {
	assert.equal(hoverOffset(0), HOVER_HEIGHT);
	assert.ok(Math.abs(hoverOffset(HOVER_BOB_SECONDS / 4) - (HOVER_HEIGHT + HOVER_BOB_HEIGHT)) < 1e-9);
	assert.ok(Math.abs(hoverOffset((HOVER_BOB_SECONDS * 3) / 4) - (HOVER_HEIGHT - HOVER_BOB_HEIGHT)) < 1e-9);
});
