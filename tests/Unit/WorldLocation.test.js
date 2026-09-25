import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createCrossingTracker, floorAt, zoneAt, zoneChain } from '../../resources/js/components/world/worldLocation.js';

const layout = {
	floors: [
		{ id: 'ground', name: 'Ground floor', minY: -2, maxY: 4 },
		{ id: 'upper', name: 'Upper floor', minY: 4, maxY: 8 },
	],
	zones: [
		{ id: 'studio', name: 'Music studio', floorId: 'ground', parentId: null, outline: [[-10, 0], [0, 0], [0, 10], [-10, 10]], minY: -2, maxY: 4 },
		{ id: 'vocal-booth', name: 'Vocal booth', floorId: 'ground', parentId: 'studio', outline: [[-4, 6], [0, 6], [0, 10], [-4, 10]], minY: -2, maxY: 4 },
		{ id: 'pool-terrace', name: 'Pool terrace', floorId: 'ground', parentId: null, outline: [[0, -10], [10, -10], [10, 0], [0, 0]], minY: -2, maxY: 4 },
		{ id: 'gallery', name: 'Gallery', floorId: 'upper', parentId: null, outline: [[-10, 0], [10, 0], [10, 10], [-10, 10]], minY: 4, maxY: 8 },
	],
};

test('floorAt picks the floor whose height range holds the point', () => {
	assert.equal(floorAt(layout, 0).id, 'ground');
	assert.equal(floorAt(layout, 5).id, 'upper');
	assert.equal(floorAt(layout, 20), null);
	assert.equal(floorAt({ floors: [] }, 0), null);
});

test('zoneAt returns the innermost zone containing the point', () => {
	assert.equal(zoneAt(layout, { x: -2, y: 0, z: 8 }).id, 'vocal-booth');
	assert.equal(zoneAt(layout, { x: -8, y: 0, z: 2 }).id, 'studio');
	assert.equal(zoneAt(layout, { x: 5, y: 0, z: -5 }).id, 'pool-terrace');
});

test('zoneAt respects the point floor and the zone height ranges', () => {
	assert.equal(zoneAt(layout, { x: -2, y: 5, z: 8 }).id, 'gallery');
	assert.equal(zoneAt(layout, { x: 5, y: 5, z: -5 }), null);
});

test('zoneAt returns null outside every outline', () => {
	assert.equal(zoneAt(layout, { x: 50, y: 0, z: 50 }), null);
});

test('zoneChain lists the zone and its ancestors, outermost first', () => {
	const booth = layout.zones.find((zone) => zone.id === 'vocal-booth');
	assert.deepEqual(zoneChain(layout, booth).map((zone) => zone.id), ['studio', 'vocal-booth']);
});

test('the crossing tracker announces the first zone and new crossings', () => {
	const tracker = createCrossingTracker({ debounceMs: 2000 });
	assert.deepEqual(tracker.update('studio', 0), { changed: true, announce: true });
	assert.deepEqual(tracker.update('studio', 100), { changed: false, announce: false });
	assert.deepEqual(tracker.update('pool-terrace', 5000), { changed: true, announce: true });
});

test('the crossing tracker stays quiet when re-entering a zone left moments ago', () => {
	const tracker = createCrossingTracker({ debounceMs: 2000 });
	tracker.update('studio', 0);
	tracker.update('pool-terrace', 1000);
	assert.deepEqual(tracker.update('studio', 1500), { changed: true, announce: false });
	assert.deepEqual(tracker.update('pool-terrace', 1800), { changed: true, announce: false });
	assert.deepEqual(tracker.update('studio', 9000), { changed: true, announce: true });
});

test('the crossing tracker reports leaving every zone without announcing it', () => {
	const tracker = createCrossingTracker({ debounceMs: 2000 });
	tracker.update('studio', 0);
	assert.deepEqual(tracker.update(null, 5000), { changed: true, announce: false });
	const fresh = createCrossingTracker({ debounceMs: 2000 });
	assert.deepEqual(fresh.update(null, 0), { changed: true, announce: false });
});
