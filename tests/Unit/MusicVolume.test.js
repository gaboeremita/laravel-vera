import assert from 'node:assert/strict';
import { test } from 'node:test';
import { DUCKED_LEVEL, musicVolume, perceivedVolume } from '../../resources/js/components/world/musicVolume.js';

test('the slider follows loudness rather than raw volume', () => {
	assert.equal(perceivedVolume(1), 1);
	assert.equal(perceivedVolume(0.5), 0.125);
	assert.equal(perceivedVolume(0), 0);
	assert.equal(perceivedVolume(1.4), 1);
});

test('the music drops while a resident speaks and is silent when muted', () => {
	assert.equal(musicVolume({ slider: 1, muted: false, ducked: true }), DUCKED_LEVEL);
	assert.equal(musicVolume({ slider: 0.5, muted: false, ducked: false }), 0.125);
	assert.equal(musicVolume({ slider: 1, muted: true, ducked: false }), 0);
});
