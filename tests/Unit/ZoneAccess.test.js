import assert from 'node:assert/strict';
import { test } from 'node:test';
import { canEnterZone, parseZoneAccess } from '../../resources/js/components/world/zoneAccess.js';

const zone = (id, extra = {}) => ({ id, parentId: null, private: false, accessTags: [], ...extra });
const layout = {
	zones: [
		zone('street'),
		zone('mona-house', { private: true }),
		zone('mona-bedroom', { parentId: 'mona-house' }),
		zone('the-orphanage', { private: true, secret: true, accessTags: ['Deprecated'] }),
		zone('war-room', { parentId: 'the-orphanage' }),
	],
};

test('anyone may enter an open zone', () => {
	assert.equal(canEnterZone(layout, 'street', null), true);
});

test('a private zone and the zones inside it keep out a resident without access', () => {
	assert.equal(canEnterZone(layout, 'mona-house', null), false);
	assert.equal(canEnterZone(layout, 'mona-bedroom', { tags: [], zones: [] }), false);
	assert.equal(canEnterZone(layout, 'war-room', { tags: ['forks'], zones: [] }), false);
});

test('access to a zone by id covers the zones inside it', () => {
	const access = { tags: [], zones: ['mona-house'] };
	assert.equal(canEnterZone(layout, 'mona-house', access), true);
	assert.equal(canEnterZone(layout, 'mona-bedroom', access), true);
	assert.equal(canEnterZone(layout, 'the-orphanage', access), false);
});

test('a group tag opens the zones tagged for it, whatever its case', () => {
	const access = { tags: ['deprecated'], zones: [] };
	assert.equal(canEnterZone(layout, 'the-orphanage', access), true);
	assert.equal(canEnterZone(layout, 'war-room', access), true);
});

test('zone access JSON parses into tags and zones', () => {
	assert.deepEqual(parseZoneAccess(''), { zoneAccess: null, error: null });
	assert.deepEqual(parseZoneAccess('{ "tags": ["deprecated"] }'), { zoneAccess: { tags: ['deprecated'], zones: [] }, error: null });
});

test('malformed zone access JSON says what is wrong', () => {
	assert.equal(parseZoneAccess('{ tags: [] }').error, 'Not valid JSON');
	assert.equal(parseZoneAccess('["deprecated"]').error, 'Must be an object with "tags" and "zones"');
	assert.equal(parseZoneAccess('{ "groups": [] }').error, 'Unknown key "groups"; only "tags" and "zones" are allowed');
	assert.equal(parseZoneAccess('{ "zones": "mona-house" }').error, '"zones" must be a list of names');
});
