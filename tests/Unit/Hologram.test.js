import assert from 'node:assert/strict';
import { test } from 'node:test';
import { Bone, Group } from 'three';
import { FLOAT_HEIGHT, GLOW_BREATH_SECONDS, SWAY_ANGLE, findOrnaments, glowBreath, moveOrnaments, ornamentMotion, ornamentPose, shimmerLevel } from '../../resources/js/utils/hologram.js';

function bone(name, parent) {
	const node = new Bone();
	node.name = name;
	parent.add(node);
	return node;
}

test('a bone moves on its own only when its name says how', () => {
	assert.equal(ornamentMotion('orbit_halo_01'), 'orbit');
	assert.equal(ornamentMotion('float_crystal_l_01'), 'float');
	assert.equal(ornamentMotion('sway_left_front_01'), 'sway');
	assert.equal(ornamentMotion('hair_l_01'), null);
	assert.equal(ornamentMotion('head'), null);
});

test('only the outermost bone of an ornament chain moves', () => {
	const root = new Group();
	const head = bone('head', root);
	const halo = bone('orbit_halo_01', head);
	bone('orbit_halo_02', halo);
	const crystal = bone('float_crystal_l_01', root);
	bone('hair_l_01', head);

	assert.deepEqual(findOrnaments(root).map((ornament) => ornament.node), [halo, crystal]);
});

test('an orbiting bone turns in place and a floating bone bobs along its own axis', () => {
	const root = new Group();
	const ring = bone('orbit_ring_01', root);
	ring.position.set(0, 1.3, 0);
	const crystal = bone('float_crystal_r_01', root);
	crystal.position.set(0.4, 1.0, 0);
	const ornaments = findOrnaments(root);

	moveOrnaments(ornaments, 3);

	assert.deepEqual(ring.position.toArray(), [0, 1.3, 0]);
	assert.notEqual(ring.quaternion.w, 1);
	assert.equal(crystal.position.x, 0.4);
	assert.ok(Math.abs(crystal.position.y - (1.0 + ornamentPose('float', 1, 3).lift)) < 1e-9);
	assert.ok(Math.abs(ornamentPose('float', 1, 3).lift) <= FLOAT_HEIGHT);
});

test('every bone of a swaying chain bends, each a little after the one above it', () => {
	const root = new Group();
	const pelvis = bone('pelvis', root);
	const first = bone('sway_left_front_01', pelvis);
	const second = bone('sway_left_front_02', first);
	const other = bone('sway_right_back_01', pelvis);
	const ornaments = findOrnaments(root);

	assert.deepEqual(ornaments.map(({ node, depth }) => [node, depth]), [[first, 0], [second, 1], [other, 0]]);
	assert.equal(ornaments[1].phase, ornaments[0].phase);
	assert.notEqual(ornaments[2].phase, ornaments[0].phase);

	moveOrnaments(ornaments, 2);

	assert.notEqual(first.quaternion.w, 1);
	assert.deepEqual(second.position.toArray(), [0, 0, 0]);
	const [upper, lower] = [ornamentPose('sway', 0, 2, 0).swing, ornamentPose('sway', 0, 2, 1).swing];
	assert.notDeepEqual(upper, lower);
	assert.ok([...upper, ...lower].every((angle) => Math.abs(angle) <= SWAY_ANGLE));
});

test('neighbouring orbits turn opposite ways', () => {
	assert.ok(ornamentPose('orbit', 0, 5).turn > 0);
	assert.ok(ornamentPose('orbit', 1, 5).turn < 0);
});

test('a hologram stays near full brightness and only dips briefly', () => {
	const levels = Array.from({ length: 600 }, (_, frame) => shimmerLevel(frame / 60, 0));
	assert.ok(levels.every((level) => level > 0.35 && level < 1.3));
	assert.ok(levels.filter((level) => level < 0.75).length < levels.length / 10);
});

test('the glow breathes slowly from dim to bright and back', () => {
	assert.equal(glowBreath(0), 0);
	assert.ok(Math.abs(glowBreath(GLOW_BREATH_SECONDS / 2) - 1) < 1e-9);
	assert.ok(Math.abs(glowBreath(GLOW_BREATH_SECONDS)) < 1e-9);
});
