import assert from 'node:assert/strict';
import { test } from 'node:test';
import { totalScore } from '../../resources/js/utils/endingScore.js';

test('the total of a single dimension is its score', () => {
	assert.equal(totalScore([{ dimension: 'kindness', score: 7 }]), 7);
});

test('the total of several dimensions is their average to one decimal', () => {
	assert.equal(totalScore([{ dimension: 'kindness', score: 7 }, { dimension: 'honesty', score: 8 }]), 7.5);
	assert.equal(totalScore([{ dimension: 'a', score: 7 }, { dimension: 'b', score: 8 }, { dimension: 'c', score: 8 }]), 7.7);
});

test('an ending without scores has no total', () => {
	assert.equal(totalScore([]), null);
	assert.equal(totalScore(undefined), null);
});
