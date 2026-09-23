import assert from 'node:assert/strict';
import { test } from 'node:test';
import { CONVERSATION_END_DISTANCE, CONVERSATION_WARNING_DISTANCE, conversationRangeState } from '../../resources/js/components/world/conversationRange.js';

test('a conversation is in range below the warning distance', () => {
	assert.equal(conversationRangeState(0), 'ok');
	assert.equal(conversationRangeState(CONVERSATION_WARNING_DISTANCE - 0.01), 'ok');
});

test('a conversation warns between the warning and end distances', () => {
	assert.equal(conversationRangeState(CONVERSATION_WARNING_DISTANCE), 'warning');
	assert.equal(conversationRangeState(CONVERSATION_END_DISTANCE - 0.01), 'warning');
});

test('a conversation ends at and beyond the end distance', () => {
	assert.equal(conversationRangeState(CONVERSATION_END_DISTANCE), 'ended');
	assert.equal(conversationRangeState(40), 'ended');
});
