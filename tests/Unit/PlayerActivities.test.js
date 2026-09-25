import assert from 'node:assert/strict';
import { test } from 'node:test';
import { activityKind, nearestFreeSpot } from '../../resources/js/components/world/playerActivities.js';

const spot = (id, x, activities = ['sit']) => ({ id, approach: { x, y: 0, z: 0 }, activities: activities.map((activityId) => ({ id: activityId })) });
const counter = { spots: [spot('stool-1', 0), spot('stool-2', 2), spot('stool-3', 4), spot('back', 1, ['mix'])] };

test('the nearest free spot offering the activity is chosen', () => {
	assert.equal(nearestFreeSpot(counter, 'sit', new Map(), { x: 3.8, z: 0 }).id, 'stool-3');
	assert.equal(nearestFreeSpot(counter, 'sit', new Map([['stool-3', [4]]]), { x: 3.8, z: 0 }).id, 'stool-2');
	assert.equal(nearestFreeSpot(counter, 'mix', new Map(), { x: 3.8, z: 0 }).id, 'back');
});

test('a spot the user already holds counts as free for them', () => {
	assert.equal(nearestFreeSpot(counter, 'sit', new Map([['stool-3', ['user']]]), { x: 3.8, z: 0 }).id, 'stool-3');
});

test('no spot is chosen when every one is taken', () => {
	const occupied = new Map([['stool-1', [1]], ['stool-2', [2]], ['stool-3', [3]]]);
	assert.equal(nearestFreeSpot(counter, 'sit', occupied, { x: 0, z: 0 }), null);
});

test('a shared spot with room left is chosen until it is full', () => {
	const bed = { spots: [{ ...spot('bed-left', 0, ['lie-down']), capacity: 2 }] };
	assert.equal(nearestFreeSpot(bed, 'lie-down', new Map([['bed-left', [7]]]), { x: 0, z: 0 }).id, 'bed-left');
	assert.equal(nearestFreeSpot(bed, 'lie-down', new Map([['bed-left', [7, 8]]]), { x: 0, z: 0 }), null);
});

test('activities are resting, standing or zone activities', () => {
	assert.equal(activityKind({ posture: 'sitting' }, false), 'resting');
	assert.equal(activityKind({ posture: 'lying' }, false), 'resting');
	assert.equal(activityKind({ posture: 'reclining' }, false), 'resting');
	assert.equal(activityKind({ posture: null }, false), 'standing');
	assert.equal(activityKind({ posture: null }, true), 'zone');
});
