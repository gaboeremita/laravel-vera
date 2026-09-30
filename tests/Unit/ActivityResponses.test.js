import assert from 'node:assert/strict';
import { test } from 'node:test';
import { blockingObjectIds, givesItem, openingActivityId, likelyResponse, mayHold, needsAttempt, requiredItemIds, responseGives, responsePrice, responseTakes } from '../../resources/js/utils/activityResponses.js';

const KEY = 7;
const DRINK = 9;
const door = {
	items: { [KEY]: { name: 'Laundry key', cardImageUrl: null }, [DRINK]: { name: 'Drink', cardImageUrl: '/drink.png' } },
	responses: [
		{ condition: { objectState: 'passable' }, effects: [{ type: 'showText', text: 'Open.' }] },
		{ condition: { all: [{ has: { item: KEY, atLeast: 1 } }, { knows: 3 }] }, effects: [{ type: 'takeCredits', amount: 2 }, { type: 'takeCredits', amount: 3 }, { type: 'takeItems', items: [{ itemId: KEY, quantity: 1 }] }, { type: 'makePassable' }] },
		{ condition: null, effects: [{ type: 'giveItems', items: [{ itemId: DRINK, quantity: 2 }] }] },
	],
};

test('guesses the first response the player can get, counting unknown leaves as met', () => {
	const withKey = { inventory: { credits: 10, items: [{ itemId: KEY, quantity: 1 }] }, objectState: {} };
	assert.equal(likelyResponse(door, withKey), door.responses[1]);
	assert.equal(likelyResponse(door, { inventory: { credits: 10, items: [] }, objectState: {} }), door.responses[2]);
	assert.equal(likelyResponse(door, { ...withKey, objectState: { passable: true } }), door.responses[0]);
	assert.equal(likelyResponse({ responses: [] }, withKey), null);
});

test('reads a negated leaf only when the client knows it', () => {
	const known = { inventory: { credits: null, items: [] }, objectState: {} };
	assert.equal(mayHold({ not: { objectState: 'passable' } }, known), true);
	assert.equal(mayHold({ not: { objectState: 'passable' } }, { ...known, objectState: { passable: true } }), false);
	assert.equal(mayHold({ not: { knows: 3 } }, known), true);
	assert.equal(mayHold({ credits: { atLeast: 1000 } }, known), true);
});

test('sums what a response charges and names what it takes and gives', () => {
	assert.equal(responsePrice(door.responses[1]), 5);
	assert.equal(responsePrice(door.responses[0]), 0);
	assert.deepEqual(responseTakes(door, door.responses[1]), [{ itemId: KEY, quantity: 1, name: 'Laundry key', cardImageUrl: null }]);
	assert.deepEqual(responseGives(door, door.responses[2]), [{ itemId: DRINK, quantity: 2, name: 'Drink', cardImageUrl: '/drink.png' }]);
	assert.equal(givesItem(door, DRINK), true);
	assert.equal(givesItem(door, KEY), false);
});

test('finds the items the first response asks for and whether the narrator is asked', () => {
	assert.deepEqual(requiredItemIds({ responses: [door.responses[1]] }), [KEY]);
	assert.deepEqual(requiredItemIds({ responses: [{ condition: { has: { item: DRINK, atLeast: 1 } }, effects: [] }] }), [DRINK]);
	assert.deepEqual(requiredItemIds(door), []);
	assert.equal(needsAttempt(door), false);
	assert.equal(needsAttempt({ responses: [{ condition: { all: [{ narrator: { requirement: 'Be kind.', outcome: '' } }] }, effects: [] }] }), true);
});

test('an object blocks when one of its responses makes it passable', () => {
	const terms = [
		{ objectId: 'orphanage-door', responses: door.responses },
		{ objectId: 'arcade', responses: [{ condition: null, effects: [{ type: 'showText', text: 'You play a round.' }] }] },
		{ objectId: 'bench', responses: [] },
	];
	assert.deepEqual([...blockingObjectIds(terms)], ['orphanage-door']);
	assert.equal(blockingObjectIds(undefined).size, 0);
});

test('finds the activity that opens a blocking object', () => {
	const terms = [
		{ objectId: 'toll-gate', activityId: 'stare-down-the-gate', responses: [{ condition: null, effects: [{ type: 'showText', text: 'You glare.' }] }] },
		{ objectId: 'toll-gate', activityId: 'pay-the-toll', responses: door.responses },
	];
	assert.equal(openingActivityId(terms, 'toll-gate'), 'pay-the-toll');
	assert.equal(openingActivityId(terms, 'bench'), null);
});
