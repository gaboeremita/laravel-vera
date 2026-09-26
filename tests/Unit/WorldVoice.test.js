import assert from 'node:assert/strict';
import { test } from 'node:test';
import { EARSHOT, readVoiceEnabled, storeVoiceEnabled, voicesLine } from '../../resources/js/components/world/worldVoice.js';

function memoryStorage() {
	const values = new Map();
	return { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
}

test('world voices start off and remember the choice', () => {
	const storage = memoryStorage();
	assert.equal(readVoiceEnabled(storage), false);
	storeVoiceEnabled(true, storage);
	assert.equal(readVoiceEnabled(storage), true);
	storeVoiceEnabled(false, storage);
	assert.equal(readVoiceEnabled(storage), false);
});

test('world voices stay off when storage is unavailable', () => {
	const blocked = { getItem: () => { throw new Error('blocked'); }, setItem: () => { throw new Error('blocked'); } };
	assert.equal(readVoiceEnabled(blocked), false);
	assert.doesNotThrow(() => storeVoiceEnabled(true, blocked));
	assert.equal(readVoiceEnabled(undefined), false);
});

test('a line is voiced only with voices on and within earshot', () => {
	assert.equal(voicesLine({ voiceEnabled: true, distance: EARSHOT }), true);
	assert.equal(voicesLine({ voiceEnabled: true, distance: EARSHOT + 0.1 }), false);
	assert.equal(voicesLine({ voiceEnabled: false, distance: 1 }), false);
});
