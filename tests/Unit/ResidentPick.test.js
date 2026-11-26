import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pickResident } from '../../resources/js/components/world/residentPick.js';

const eye = { x: 0, y: 1.6, z: 0 };
const ahead = { x: 0, y: 0, z: -1 };

test('a ray through a resident picks them', () => {
	assert.equal(pickResident(eye, ahead, new Map([[7, { x: 0.2, y: 0, z: -5 }]])), 7);
});

test('a ray beside, over or behind a resident picks nobody', () => {
	assert.equal(pickResident(eye, ahead, new Map([[7, { x: 1.5, y: 0, z: -5 }]])), null);
	assert.equal(pickResident(eye, ahead, new Map([[7, { x: 0, y: -1, z: -5 }]])), null);
	assert.equal(pickResident(eye, ahead, new Map([[7, { x: 0, y: 0, z: 5 }]])), null);
});

test('of two residents in line, the nearer one is picked', () => {
	assert.equal(pickResident(eye, ahead, new Map([[7, { x: 0, y: 0, z: -9 }], [8, { x: 0, y: 0, z: -4 }]])), 8);
});

test('a ray looking down at a resident\'s head picks them', () => {
	const down = { x: 0, y: -0.6, z: -0.8 };
	assert.equal(pickResident({ x: 0, y: 3.5, z: 0 }, down, new Map([[7, { x: 0, y: 0, z: -2.2 }]])), 7);
});
