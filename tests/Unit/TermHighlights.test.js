import assert from 'node:assert/strict';
import { test } from 'node:test';
import { underlinedParts } from '../../resources/js/utils/termHighlights.js';

test('returns the whole text as one plain part without ranges', () => {
	assert.deepEqual(underlinedParts('alpha beta', []), [{ text: 'alpha beta', underlined: false }]);
});

test('underlines one range in the middle', () => {
	assert.deepEqual(underlinedParts('alpha beta gamma', [[6, 4]]), [
		{ text: 'alpha ', underlined: false },
		{ text: 'beta', underlined: true },
		{ text: ' gamma', underlined: false },
	]);
});

test('underlines several ranges given out of order', () => {
	assert.deepEqual(underlinedParts('alpha beta gamma', [[11, 5], [0, 5]]), [
		{ text: 'alpha', underlined: true },
		{ text: ' beta ', underlined: false },
		{ text: 'gamma', underlined: true },
	]);
});

test('underlines ranges at the start and the end', () => {
	assert.deepEqual(underlinedParts('alpha beta', [[0, 5], [6, 4]]), [
		{ text: 'alpha', underlined: true },
		{ text: ' ', underlined: false },
		{ text: 'beta', underlined: true },
	]);
});

test('indexes UTF-16 code units, so ranges after an emoji land on the right word', () => {
	assert.deepEqual(underlinedParts('😀 beta', [[3, 4]]), [
		{ text: '😀 ', underlined: false },
		{ text: 'beta', underlined: true },
	]);
});
