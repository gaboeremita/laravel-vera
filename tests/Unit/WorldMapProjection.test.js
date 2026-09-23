import assert from 'node:assert/strict';
import { test } from 'node:test';
import { floorBounds, floorForHeight, floorGroundHeight, projectToMap, spreadLabels } from '../../resources/js/components/world/worldMapProjection.js';

const layout = {
	floors: [
		{ id: 'ground', name: 'Ground floor', minY: -2, maxY: 4 },
		{ id: 'upper', name: 'Upper floor', minY: 4, maxY: 8 },
	],
	zones: [
		{ id: 'studio', floorId: 'ground', outline: [[-10, 0], [0, 0], [0, 10], [-10, 10]], entry: { x: -5, y: 0, z: 1 } },
		{ id: 'pool', floorId: 'ground', outline: [[0, -10], [10, -10], [10, 0], [0, 0]], entry: { x: 5, y: -1.4, z: -5 } },
		{ id: 'terrace', floorId: 'ground', outline: [[10, -10], [12, -10], [12, 0], [10, 0]], entry: { x: 11, y: 0, z: -5 } },
		{ id: 'gallery', floorId: 'upper', outline: [[-10, 0], [10, 0], [10, 10], [-10, 10]], entry: { x: 0, y: 4, z: 5 } },
	],
};

test('floors are chosen by height range, including the boundary between floors', () => {
	assert.equal(floorForHeight(layout, 0).id, 'ground');
	assert.equal(floorForHeight(layout, 4).id, 'upper');
	assert.equal(floorForHeight(layout, 9), null);
	assert.equal(floorForHeight({ floors: [] }, 0), null);
});

test('floor bounds cover the zone outlines of that floor with padding', () => {
	assert.deepEqual(floorBounds(layout, 'upper'), { minX: -11.5, maxX: 11.5, minZ: -1.5, maxZ: 11.5 });
	assert.equal(floorBounds({ zones: [] }, null), null);
});

test('the ground height of a floor is the median entry height, ignoring a sunken pool', () => {
	assert.equal(floorGroundHeight(layout, 'ground'), 0);
	assert.equal(floorGroundHeight({ zones: [] }, null, 1.5), 1.5);
});

test('world points project onto the map with north up and east right', () => {
	const bounds = { minX: -10, maxX: 10, minZ: -5, maxZ: 5 };
	assert.deepEqual(projectToMap({ x: -10, z: -5 }, bounds, { width: 200, height: 100 }), { x: 0, y: 0 });
	assert.deepEqual(projectToMap({ x: 10, z: 5 }, bounds, { width: 200, height: 100 }), { x: 200, y: 100 });
	assert.deepEqual(projectToMap({ x: 0, z: 0 }, bounds, { width: 200, height: 100 }), { x: 100, y: 50 });
});

test('overlapping labels are pushed apart so each stays readable', () => {
	const spread = spreadLabels([{ id: 'a', x: 50, y: 50 }, { id: 'b', x: 52, y: 51 }, { id: 'c', x: 200, y: 50 }], 14);
	const a = spread.find((marker) => marker.id === 'a');
	const b = spread.find((marker) => marker.id === 'b');
	const c = spread.find((marker) => marker.id === 'c');
	assert.ok(Math.abs(a.labelY - b.labelY) >= 14);
	assert.equal(c.labelY, 50);
});
