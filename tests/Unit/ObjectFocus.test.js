import assert from 'node:assert/strict';
import { test } from 'node:test';
import { objectsWithin, pickFocus, reachPoints, spotAvailability } from '../../resources/js/components/world/objectFocus.js';

const spot = (id, x, z, activityId = 'sit') => ({ id, position: { x, y: 0.5, z }, approach: { x, y: 0.5, z: z + 0.6 }, facing: 0, activities: [{ id: activityId, name: 'Sit down', posture: 'sitting' }] });

const layout = {
	floors: [
		{ id: 'ground', minY: -2, maxY: 4 },
		{ id: 'upper', minY: 4, maxY: 8 },
	],
	objects: [
		{ id: 'keyboards', position: { x: 0, y: 0, z: 0 }, spots: [] },
		{ id: 'keytars', position: { x: 1.5, y: 0, z: 0 }, spots: [] },
		{ id: 'bench', position: { x: 10, y: 0, z: 0 }, spots: [spot('bench-1', 8.5, 0), spot('bench-2', 10, 0), spot('bench-3', 11.5, 0)] },
		{ id: 'telescope', position: { x: 0, y: 5, z: 0 }, spots: [] },
	],
};

test('reach points are the object and each of its spots approach points', () => {
	assert.equal(reachPoints(layout.objects[2]).length, 4);
});

test('nothing is in focus beyond reach', () => {
	assert.equal(pickFocus({ layout, foot: { x: 0, y: 0, z: 5 }, forward: { x: 0, z: -1 }, floorId: 'ground' }), null);
});

test('objects on another floor never gain focus', () => {
	const focused = pickFocus({ layout, foot: { x: 0, y: 5, z: 1 }, forward: { x: 0, z: -1 }, floorId: 'upper' });
	assert.equal(focused.id, 'telescope');
	assert.notEqual(pickFocus({ layout, foot: { x: 0, y: 0, z: 1 }, forward: { x: 0, z: -1 }, floorId: 'ground' }).id, 'telescope');
});

test('the object looked at wins over a nearer one', () => {
	const focused = pickFocus({ layout, foot: { x: 0.2, y: 0, z: 1 }, forward: { x: 1.3, z: -1 }, floorId: 'ground' });
	assert.equal(focused.id, 'keytars');
});

test('the nearest object wins when none is looked at', () => {
	const focused = pickFocus({ layout, foot: { x: 0.2, y: 0, z: 1 }, forward: { x: 0, z: 1 }, floorId: 'ground' });
	assert.equal(focused.id, 'keyboards');
});

test('a wide object is reachable from beside its far spot', () => {
	const focused = pickFocus({ layout, foot: { x: 12.5, y: 0, z: 1.5 }, forward: { x: 0, z: -1 }, floorId: 'ground' });
	assert.equal(focused.id, 'bench');
});

test('objectsWithin lists same-floor objects inside the radius', () => {
	assert.deepEqual(objectsWithin(layout, { x: 0, y: 0, z: 0 }, 'ground', 6), ['keyboards', 'keytars']);
});

test('availability counts free spots and names the holders', () => {
	const bench = layout.objects[2];
	const occupied = new Map([['bench-1', 7], ['bench-3', 'user']]);
	assert.deepEqual(spotAvailability(bench, 'sit', occupied, new Map([[7, 'Vera']])), { free: 1, total: 3, takenBy: ['Vera', 'YOU'] });
	assert.deepEqual(spotAvailability(bench, 'sit', new Map(), new Map()), { free: 3, total: 3, takenBy: [] });
});
