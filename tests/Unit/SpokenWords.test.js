import assert from 'node:assert/strict';
import test from 'node:test';
import { spokenWords } from '../../resources/js/utils/parsers.js';

test('a transcript of only sounds is nothing to send', () => {
	for (const noise of ['(water splashing)', '[Music]', '*coughs*', '♪ ♪', '(coughing) [metal clanking]', '  ']) {
		assert.equal(spokenWords(noise), '', noise);
	}
});

test('what she hears is the words, without the sounds around them', () => {
	assert.equal(spokenWords('(coughing) Hey, come here'), 'Hey, come here');
	assert.equal(spokenWords('Are you there? [door closes]'), 'Are you there?');
	assert.equal(spokenWords('Swim to the edge'), 'Swim to the edge');
});
