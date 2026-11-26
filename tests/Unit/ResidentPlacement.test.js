import assert from 'node:assert/strict';
import { test } from 'node:test';
import { placementAlong } from '../../resources/js/components/world/residentPlacement.js';

const ray = { origin: { x: 0, y: 1.6, z: 0 }, direction: { x: 0, y: 0, z: -1 } };
const wallHit = { x: 0, y: 1.2, z: -3 };
const floor = { x: 0, y: 0, z: -3 };

function worldHitting(position, { stuck = false } = {}) {
	return {
		octree: { rayIntersect: () => (position ? { position } : false) },
		groundBelow: (point) => ({ ...point, y: 0 }),
		isBodyStuck: () => stuck,
	};
}

test('a ray that meets nothing has no placement', () => {
	const navigation = { isComplete: true, standingPointNear: () => assert.fail('nothing to stand near') };
	assert.equal(placementAlong(ray, worldHitting(null), navigation), null);
});

test('a finished grid moves the ground under the hit to the nearest standing point', () => {
	const standing = { x: 0.2, y: 0, z: -2.8 };
	const navigation = {
		isComplete: true,
		standingPointNear: (point) => {
			assert.deepEqual(point, floor);
			return standing;
		},
	};
	assert.equal(placementAlong(ray, worldHitting(wallHit), navigation), standing);
});

test('a finished grid with no standing point nearby has no placement', () => {
	assert.equal(placementAlong(ray, worldHitting(wallHit), { isComplete: true, standingPointNear: () => null }), null);
});

test('while the grid builds, the ground under the hit is used when a body fits there', () => {
	const building = { isComplete: false, standingPointNear: () => assert.fail('the grid is not ready') };
	assert.deepEqual(placementAlong(ray, worldHitting(wallHit), building), floor);
	assert.deepEqual(placementAlong(ray, worldHitting(wallHit), null), floor);
	assert.equal(placementAlong(ray, worldHitting(wallHit, { stuck: true }), building), null);
});
