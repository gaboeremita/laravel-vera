import assert from 'node:assert/strict';
import { test } from 'node:test';
import { AnimationClip, Bone, Group, QuaternionKeyframeTrack, VectorKeyframeTrack } from 'three';
import { mixamoBaseClip, retargetBaseClip } from '../../resources/js/utils/mixamoRetargeting.js';
import { mixamoBaseClipFor } from '../../resources/js/utils/poseClipCache.js';
import { shouldLoad, shouldUnload, summarizeResidents, updateVrm, visualDetail } from '../../resources/js/components/world/residentDetail.js';
import { createLineCache, facesPoint, voicesLine } from '../../resources/js/components/world/worldVoice.js';
import { onSameFloor } from '../../resources/js/components/world/worldLocation.js';

function fakeFbx() {
	const root = new Group();
	const hips = new Bone(); hips.name = 'mixamorigHips'; hips.position.set(0, 100, 0);
	const spine = new Bone(); spine.name = 'mixamorigSpine'; spine.quaternion.setFromAxisAngle({ x: 0, y: 0, z: 1 }, 0.3);
	hips.add(spine); root.add(hips); root.updateMatrixWorld(true);
	root.animations = [new AnimationClip('mixamo.com', 1, [
		new QuaternionKeyframeTrack('mixamorigSpine.quaternion', [0, 1], [0, 0, 0, 1, 0, 0.7071, 0, 0.7071]),
		new VectorKeyframeTrack('mixamorigHips.position', [0, 1], [0, 100, 0, 10, 100, 0]),
	])];
	return root;
}

function fakeVrm({ metaVersion = '1', hipsY = 1 } = {}) {
	const scene = new Group();
	const nodes = { hips: Object.assign(new Group(), { name: 'Normalized_hips' }), spine: Object.assign(new Group(), { name: 'Normalized_spine' }) };
	nodes.hips.position.y = hipsY; scene.add(nodes.hips); scene.updateMatrixWorld(true);
	return { scene, meta: { metaVersion }, humanoid: { getNormalizedBoneNode: (name) => nodes[name] ?? null } };
}

test('one base clip fits every VRM and is never changed by fitting it', () => {
	const base = mixamoBaseClip(fakeFbx());
	const before = base.tracks.map((track) => Array.from(track.values));
	const tall = retargetBaseClip(base, fakeVrm({ hipsY: 1 }));
	const vrm0 = retargetBaseClip(base, fakeVrm({ metaVersion: '0', hipsY: 0.5 }));
	assert.deepEqual(base.tracks.map((track) => Array.from(track.values)), before);
	assert.equal(tall.tracks[0].name, 'Normalized_spine.quaternion');
	assert.equal(tall.tracks[1].name, 'Normalized_hips.position');
	assert.ok(Math.abs(tall.tracks[1].values[3] - 0.1) < 1e-6);
	assert.ok(Math.abs(vrm0.tracks[1].values[3] + 0.05) < 1e-6);
	assert.ok(Math.abs(vrm0.tracks[0].values[4] + tall.tracks[0].values[4]) < 1e-6);
});

test('a pose file is parsed once, read back from storage when it was kept, and retried after a failure', async () => {
	let loads = 0;
	const load = async () => { loads += 1; return fakeFbx(); };
	const written = [];
	const first = await mixamoBaseClipFor('https://x/pose-a.fbx', { load, read: async () => null, write: async (url) => written.push(url) });
	const again = await mixamoBaseClipFor('https://x/pose-a.fbx', { load, read: async () => null, write: async () => {} });
	assert.equal(loads, 1);
	assert.equal(first, again);
	assert.deepEqual(written, ['https://x/pose-a.fbx']);

	const stored = { duration: 1, motionHipsHeight: 1, tracks: [] };
	assert.equal(await mixamoBaseClipFor('https://x/pose-b.fbx', { load, read: async () => stored }), stored);
	assert.equal(loads, 1);

	await assert.rejects(mixamoBaseClipFor('https://x/pose-c.fbx', { load: async () => { throw new Error('offline'); }, read: async () => null }));
	await mixamoBaseClipFor('https://x/pose-c.fbx', { load, read: async () => null, write: async () => {} });
	assert.equal(loads, 2);
});

test('residents who move or decide stay loaded anywhere; residents who stay put load near and unload far', () => {
	assert.equal(shouldLoad({ behavior: 'route', distance: 80 }), true);
	assert.equal(shouldLoad({ behavior: 'autonomous', distance: 80 }), true);
	assert.equal(shouldLoad({ behavior: 'stationary', distance: 80 }), false);
	assert.equal(shouldLoad({ behavior: 'stationary', distance: 20 }), true);
	assert.equal(shouldUnload({ behavior: 'stationary', distance: 50 }), true);
	assert.equal(shouldUnload({ behavior: 'stationary', distance: 40 }), false);
	assert.equal(shouldUnload({ behavior: 'roam', distance: 90 }), false);
});

test('bodies animate only in view, with hair physics up close and half the frame rate far away', () => {
	assert.deepEqual(visualDetail({ distance: 5, visible: true }), { animate: true, springBones: true, frameStride: 1 });
	assert.deepEqual(visualDetail({ distance: 15, visible: true }), { animate: true, springBones: false, frameStride: 1 });
	assert.deepEqual(visualDetail({ distance: 25, visible: true }), { animate: true, springBones: false, frameStride: 2 });
	assert.equal(visualDetail({ distance: 5, visible: false }).animate, false);
	assert.equal(visualDetail({ distance: 35, visible: true }).animate, false);
	assert.deepEqual(summarizeResidents(new Map([[1, visualDetail({ distance: 5, visible: true })], [2, visualDetail({ distance: 25, visible: true })], [3, visualDetail({ distance: 5, visible: false })]])), { loaded: 3, animated: 2, springBones: 1 });
});

test('the VRM update leaves the spring bones out when asked', () => {
	const calls = [];
	const vrm = { humanoid: { update: () => calls.push('humanoid') }, expressionManager: { update: () => calls.push('expressions') }, springBoneManager: { update: () => calls.push('springs') } };
	updateVrm(vrm, 0.016, false);
	assert.deepEqual(calls, ['humanoid', 'expressions']);
	updateVrm(vrm, 0.016, true);
	assert.deepEqual(calls.slice(2), ['humanoid', 'expressions', 'springs']);
});

test('a line is voiced only for a speaker the user faces, or for a line said to the user', () => {
	const view = { x: 0, z: 0, yaw: 0 };
	assert.equal(facesPoint(view, { x: 0, z: -5 }), true);
	assert.equal(facesPoint(view, { x: 0, z: 5 }), false);
	assert.equal(facesPoint(view, { x: 5, z: 0 }), false);
	assert.equal(facesPoint({ x: 0, z: 0, yaw: Math.PI / 2 }, { x: -5, z: 0 }), true);
	assert.equal(voicesLine({ voiceEnabled: true, distance: 3, facing: false }), false);
	assert.equal(voicesLine({ voiceEnabled: true, distance: 3, facing: false, toUser: true }), true);
});

test('the line cache keeps the most recent lines and forgets the oldest', () => {
	const cache = createLineCache(2);
	cache.set('a', 1); cache.set('b', 2);
	assert.equal(cache.get('a'), 1);
	cache.set('c', 3);
	assert.equal(cache.get('b'), null);
	assert.equal(cache.get('a'), 1);
	assert.equal(cache.get('c'), 3);
});

test('two points share a floor by height, and a world without floors is one floor', () => {
	const layout = { floors: [{ id: 'street', minY: -1, maxY: 3 }, { id: 'basement', minY: -6, maxY: -1 }] };
	assert.equal(onSameFloor(layout, { y: 0 }, { y: 2 }), true);
	assert.equal(onSameFloor(layout, { y: 0 }, { y: -4 }), false);
	assert.equal(onSameFloor({ floors: [] }, { y: 0 }, { y: -4 }), true);
	assert.equal(onSameFloor(layout, null, { y: -4 }), true);
});

test('she wears her low-detail model beyond 18 m and her full one again inside 15 m', async () => {
	const { wantsLod } = await import('../../resources/js/components/world/residentDetail.js');
	assert.equal(wantsLod({ hasLod: true, distance: 17, wearingLod: false }), false);
	assert.equal(wantsLod({ hasLod: true, distance: 19, wearingLod: false }), true);
	assert.equal(wantsLod({ hasLod: true, distance: 16, wearingLod: true }), true);
	assert.equal(wantsLod({ hasLod: true, distance: 14, wearingLod: true }), false);
	assert.equal(wantsLod({ hasLod: false, distance: 40, wearingLod: false }), false);
});

test('far from the user a resident decides every one to two minutes and a conversation waits 30 s between lines', async () => {
	const { decisionPace, isFar, turnGap } = await import('../../resources/js/components/world/residentDetail.js');
	const user = { x: 0, y: 0, z: 0 };
	assert.equal(isFar({ x: 29, y: 0, z: 0 }, user), false);
	assert.equal(isFar({ x: 31, y: 0, z: 0 }, user), true);
	assert.equal(isFar(null, user), false);
	assert.deepEqual(decisionPace({ min: 30, max: 60 }, true), { min: 60, max: 120 });
	assert.deepEqual(decisionPace({ min: 30, max: 60 }, false), { min: 30, max: 60 });
	assert.equal(decisionPace(null, false), null);
	assert.equal(turnGap(5000, true), 30000);
	assert.equal(turnGap(5000, false), 5000);
});

test('a grid without any ground is neither kept nor restored', async () => {
	const { createNavigationGrid } = await import('../../resources/js/components/world/worldNavigation.js');
	const options = { bounds: { minX: 0, maxX: 1, minZ: 0, maxZ: 1 }, minY: -1, maxY: 1 };
	const empty = createNavigationGrid({}, options);
	assert.equal(empty.hasGround, false);
	const restored = createNavigationGrid({}, options);
	assert.equal(restored.restore(empty.snapshot()), false);
	const ground = empty.snapshot();
	ground.heights[0] = 0;
	assert.equal(restored.restore(ground), true);
	assert.equal(restored.hasGround, true);
});
