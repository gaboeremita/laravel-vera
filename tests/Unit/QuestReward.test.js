import assert from 'node:assert/strict';
import { test } from 'node:test';
import { rewardSummary } from '../../resources/js/utils/questReward.js';

test('lists each item with its quantity, then the credits', () => {
	assert.equal(rewardSummary({ items: [{ name: 'Spray can', quantity: 1 }, { name: 'Prize tickets', quantity: 3 }], credits: 20 }), 'Spray can · Prize tickets ×3 · 20 CR');
});

test('is empty when nothing was given', () => {
	assert.equal(rewardSummary({ items: [], credits: 0 }), '');
	assert.equal(rewardSummary(null), '');
});
