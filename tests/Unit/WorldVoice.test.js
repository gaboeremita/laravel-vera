import assert from 'node:assert/strict';
import { test } from 'node:test';
import { EARSHOT, deliverLine, readVoiceEnabled, storeVoiceEnabled, voicesLine } from '../../resources/js/components/world/worldVoice.js';

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

function recordingLine(overrides = {}) {
	const calls = [];
	return {
		calls,
		options: {
			voiced: true,
			synthesize: async () => { calls.push('synthesize'); return 'audio'; },
			play: async () => { calls.push('play'); return 3; },
			gesture: () => calls.push('gesture'),
			estimate: () => 2,
			talk: (seconds) => calls.push(`talk ${seconds}`),
			...overrides,
		},
	};
}

test('the gesture starts once the voice is ready, together with it', async () => {
	const { calls, options } = recordingLine();
	assert.equal(await deliverLine(options), 3);
	assert.deepEqual(calls, ['synthesize', 'gesture', 'play']);
});

test('an unvoiced line gestures and talks for its reading time', async () => {
	const { calls, options } = recordingLine({ voiced: false });
	assert.equal(await deliverLine(options), 2);
	assert.deepEqual(calls, ['gesture', 'talk 2']);
});

test('a failed voice falls back to talking and still gestures once', async () => {
	const errors = [];
	const { calls, options } = recordingLine({ synthesize: async () => { throw new Error('offline'); }, onError: (error) => errors.push(error.message) });
	assert.equal(await deliverLine(options), 2);
	assert.deepEqual(calls, ['gesture', 'talk 2']);
	assert.deepEqual(errors, ['offline']);
});

test('a voice that does not play gestures once and talks instead', async () => {
	const { calls, options } = recordingLine({ play: async () => { calls.push('play'); return undefined; } });
	assert.equal(await deliverLine(options), 2);
	assert.deepEqual(calls, ['synthesize', 'gesture', 'play', 'talk 2']);
});
