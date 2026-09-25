import assert from 'node:assert/strict';
import { test } from 'node:test';
import { claimSpot, fullSpotIds, hasRoomFor, holderUnder, holdersOf, releaseAllSpots, releaseSpot, spotCapacity, stackTier, stackedSpots } from '../../resources/js/components/world/spotOccupancy.js';

const chair = { id: 'chair' };
const bed = { id: 'bed-left', capacity: 2 };

test('a spot holds one body unless it says otherwise', () => {
	assert.equal(spotCapacity(chair), 1);
	assert.equal(spotCapacity(bed), 2);
});

test('an ordinary spot takes one holder and refuses the next', () => {
	const occupied = new Map();
	assert.equal(claimSpot(occupied, chair, 7), true);
	assert.equal(claimSpot(occupied, chair, 'user'), false);
	assert.deepEqual(holdersOf(occupied, 'chair'), [7]);
});

test('a shared spot takes holders in arrival order up to its capacity', () => {
	const occupied = new Map();
	assert.equal(claimSpot(occupied, bed, 7), true);
	assert.equal(claimSpot(occupied, bed, 'user'), true);
	assert.equal(claimSpot(occupied, bed, 8), false);
	assert.deepEqual(holdersOf(occupied, 'bed-left'), [7, 'user']);
});

test('claiming a spot twice keeps a single hold', () => {
	const occupied = new Map();
	claimSpot(occupied, bed, 7);
	assert.equal(claimSpot(occupied, bed, 7), true);
	assert.deepEqual(holdersOf(occupied, 'bed-left'), [7]);
	assert.equal(hasRoomFor(occupied, bed, 7), true);
});

test('the second holder lies on top and drops down when the first leaves', () => {
	const occupied = new Map();
	claimSpot(occupied, bed, 7);
	claimSpot(occupied, bed, 'user');
	assert.equal(stackTier(occupied, 'bed-left', 7), 0);
	assert.equal(stackTier(occupied, 'bed-left', 'user'), 1);
	releaseSpot(occupied, 'bed-left', 7);
	assert.equal(stackTier(occupied, 'bed-left', 'user'), 0);
});

test('releasing the last holder frees the spot', () => {
	const occupied = new Map();
	claimSpot(occupied, chair, 7);
	releaseSpot(occupied, 'chair', 8);
	assert.deepEqual(holdersOf(occupied, 'chair'), [7]);
	releaseSpot(occupied, 'chair', 7);
	assert.equal(occupied.has('chair'), false);
});

test('releasing everything a holder has leaves the others in place', () => {
	const occupied = new Map();
	claimSpot(occupied, chair, 'user');
	claimSpot(occupied, bed, 7);
	claimSpot(occupied, bed, 'user');
	releaseAllSpots(occupied, 'user');
	assert.equal(occupied.has('chair'), false);
	assert.deepEqual(holdersOf(occupied, 'bed-left'), [7]);
});

test('only spots with no room left count as full for someone', () => {
	const layout = { objects: [{ spots: [chair, bed] }] };
	const occupied = new Map();
	claimSpot(occupied, chair, 'user');
	claimSpot(occupied, bed, 'user');
	assert.deepEqual(fullSpotIds(occupied, layout, 7), ['chair']);
	claimSpot(occupied, bed, 8);
	assert.deepEqual(fullSpotIds(occupied, layout, 7), ['chair', 'bed-left']);
	assert.deepEqual(fullSpotIds(occupied, layout, 8), ['chair']);
});

test('the holder underneath is known only for the one on top', () => {
	const occupied = new Map();
	claimSpot(occupied, bed, 7);
	claimSpot(occupied, bed, 'user');
	assert.equal(holderUnder(occupied, 'bed-left', 'user'), 7);
	assert.equal(holderUnder(occupied, 'bed-left', 7), null);
	assert.equal(holderUnder(occupied, 'chair', 'user'), null);
});

test('only spots with more than one body are reported as stacked, bottom first', () => {
	const occupied = new Map();
	claimSpot(occupied, chair, 'user');
	claimSpot(occupied, bed, 7);
	claimSpot(occupied, bed, 'user');
	assert.deepEqual(stackedSpots(occupied), [{ spotId: 'bed-left', holders: ['7', 'user'] }]);
});
