import assert from 'node:assert/strict';
import { test } from 'node:test';
import { actionLine, getUpLine, joinLines, observationGetUpLine, observationLine } from '../../resources/js/components/world/activityLines.js';

const object = (name) => ({ name });
const activity = (name) => ({ name });

test('action lines put the activity in the third person at its object', () => {
	assert.equal(actionLine({ activity: activity('Sit down'), object: object('Bar counter') }), '*sits down at the bar counter*');
	assert.equal(actionLine({ activity: activity('Have a drink'), object: object('Pool bar') }), '*has a drink at the pool bar*');
	assert.equal(actionLine({ activity: activity('Watch TV'), object: object('Sunken sofa') }), '*watches TV at the sunken sofa*');
	assert.equal(actionLine({ activity: activity('Wash the dishes'), object: object('Kitchen sink') }), '*washes the dishes at the kitchen sink*');
	assert.equal(actionLine({ activity: activity('Make coffee'), object: object('Back counter') }), '*makes coffee at the back counter*');
});

test('action lines skip the object when the activity already names it', () => {
	assert.equal(actionLine({ activity: activity('Play the Rhodes'), object: object('Rhodes piano') }), '*plays the Rhodes*');
});

test('zone activities have no object', () => {
	assert.equal(actionLine({ activity: activity('Look out at the city') }), '*looks out at the city*');
});

test('getting up names the object left', () => {
	assert.equal(getUpLine(object('Bar counter')), '*gets up from the bar counter*');
});

test('queued lines join with a space', () => {
	assert.equal(joinLines(['*sits down*', '*gets up*']), '*sits down* *gets up*');
});

test('observations describe the activity in the resident own voice', () => {
	assert.equal(observationLine({ activity: activity('Sit down'), object: object('Bar counter') }), '*I see the user sit down at the bar counter*');
	assert.equal(observationLine({ activity: activity('Make coffee'), object: object('Back counter') }), '*I see the user make coffee at the back counter*');
	assert.equal(observationLine({ activity: activity('Play the Rhodes'), object: object('Rhodes piano') }), '*I see the user play the Rhodes*');
	assert.equal(observationLine({ activity: activity('Look out at the city') }), '*I see the user look out at the city*');
	assert.equal(observationGetUpLine(object('Bar counter')), '*I see the user get up from the bar counter*');
});
