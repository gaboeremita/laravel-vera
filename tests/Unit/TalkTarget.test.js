import assert from 'node:assert/strict';
import { test } from 'node:test';
import { INTERACTION_DISTANCE, chooseTalkTarget } from '../../resources/js/components/world/talkTarget.js';

const foot = { x: 0, y: 0, z: 0 };
const vera = { id: 1 };
const marta = { id: 2 };
const residents = [vera, marta];

test('without a pick, the nearest resident within reach is the target', () => {
	const positions = new Map([[1, { x: 1, y: 0, z: 0 }], [2, { x: 3, y: 0, z: 0 }]]);
	assert.equal(chooseTalkTarget(residents, positions, foot), vera);
});

test('a picked resident within reach is the target over a nearer one', () => {
	const positions = new Map([[1, { x: 1, y: 0, z: 0 }], [2, { x: 3, y: 0, z: 0 }]]);
	assert.equal(chooseTalkTarget(residents, positions, foot, 2), marta);
});

test('a picked resident out of reach gives way to the nearest one', () => {
	const positions = new Map([[1, { x: 1, y: 0, z: 0 }], [2, { x: INTERACTION_DISTANCE + 1, y: 0, z: 0 }]]);
	assert.equal(chooseTalkTarget(residents, positions, foot, 2), vera);
});

test('a resident on another floor is never the target', () => {
	const positions = new Map([[1, { x: 1, y: 3, z: 0 }]]);
	assert.equal(chooseTalkTarget(residents, positions, foot, 1), null);
});
