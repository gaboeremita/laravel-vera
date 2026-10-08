import assert from 'node:assert/strict';
import { test } from 'node:test';
import { Object3D } from 'three';
import { retargetBaseClip } from '../../resources/js/utils/mixamoRetargeting.js';

function vrmWithHipsAt([x, y, z], metaVersion = '1') {
	const scene = new Object3D();
	const hips = new Object3D();
	hips.name = 'Normalized_hips';
	hips.position.set(x, y, z);
	scene.add(hips);
	scene.updateWorldMatrix(true, true);

	return {
		scene,
		meta: { metaVersion },
		humanoid: {
			getNormalizedBoneNode: (boneName) => (boneName === 'hips' ? hips : null),
			normalizedRestPose: { hips: { position: [x, y, z] } },
		},
	};
}

const baseClip = {
	duration: 1,
	motionHipsHeight: 1,
	tracks: [{ bone: 'mixamorigHips', property: 'position', kind: 'vector', times: Float32Array.from([0, 1]), values: Float32Array.from([0, 1, 0, 0.1, 1, 0.2]) }],
};

function hipsValues(clip) {
	return Array.from(clip.tracks.find((track) => track.name === 'Normalized_hips.position').values, (v) => Number(v.toFixed(4)));
}

test('the hips stay where a VRM built away from the origin places them', () => {
	const clip = retargetBaseClip(baseClip, vrmWithHipsAt([1.3, 0.98, 0.37]));

	assert.deepEqual(hipsValues(clip), [1.3, 0.98, 0.37, 1.398, 0.98, 0.566]);
});

test('the hips follow the motion unchanged for a VRM centred on the origin', () => {
	const clip = retargetBaseClip(baseClip, vrmWithHipsAt([0, 0.98, 0]));

	assert.deepEqual(hipsValues(clip), [0, 0.98, 0, 0.098, 0.98, 0.196]);
});

test('a VRM 0.x mirrors the motion before adding its rest offset', () => {
	const clip = retargetBaseClip(baseClip, vrmWithHipsAt([-1.3, 0.98, -0.37], '0'));

	assert.deepEqual(hipsValues(clip), [-1.3, 0.98, -0.37, -1.398, 0.98, -0.566]);
});
