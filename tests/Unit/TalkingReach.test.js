import assert from 'node:assert/strict';
import { test } from 'node:test';
import { TALKING_DISTANCE, inTalkingReach } from '../../resources/js/components/world/talkingReach.js';

const clear = () => true;
const here = { x: 0, y: 0, z: 0 };

test('two people a step apart with nothing between them can talk', () => {
	assert.equal(inTalkingReach(here, { x: 1, y: 0, z: 0.5 }, clear), true);
});

test('someone beyond talking distance is out of reach', () => {
	assert.equal(inTalkingReach(here, { x: TALKING_DISTANCE + 0.1, y: 0, z: 0 }, clear), false);
});

test('two people in neighbouring armchairs can talk without getting up', () => {
	assert.equal(inTalkingReach(here, { x: 1.6, y: 0, z: 0 }, clear), true);
});

test('something between them puts them out of reach however close they are', () => {
	assert.equal(inTalkingReach(here, { x: 1, y: 0, z: 0 }, () => false), false);
});

test('someone on another level is out of reach', () => {
	assert.equal(inTalkingReach(here, { x: 0.5, y: 3, z: 0 }, clear), false);
});

test('the view between them is checked at eye height', () => {
	const seen = [];
	inTalkingReach(here, { x: 1, y: 0, z: 0 }, (from, to) => { seen.push(from.y, to.y); return true; });
	assert.deepEqual(seen, [1.5, 1.5]);
});
