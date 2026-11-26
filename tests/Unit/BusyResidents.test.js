import assert from 'node:assert/strict';
import { test } from 'node:test';
import { busyOthers } from '../../resources/js/components/world/busyResidents.js';

test('a resident busy with someone else is busy, with whom they are busy', () => {
	const busy = busyOthers([{ id: 2, talkingWith: 3 }, { id: 3, talkingWith: 2 }], 1);
	assert.deepEqual([...busy], [[2, 3], [3, 2]]);
});

test('a resident busy only with the chosen resident is left out', () => {
	const busy = busyOthers([{ id: 1, talkingWith: 2 }, { id: 2, talkingWith: 1 }], 1);
	assert.equal(busy.size, 0);
});
