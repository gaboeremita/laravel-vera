import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatAmount, inventoryChanges } from '../../resources/js/components/world/inventoryChanges.js';

const inventory = (credits, items) => ({ credits, items: items.map(([itemId, name, quantity]) => ({ itemId, name, quantity })) });

test('lists credit and item changes, credits first and items by name', () => {
	const before = inventory(100, [[1, 'Iron key', 1], [2, 'Bread', 1]]);
	const after = inventory(70, [[2, 'Bread', 3], [3, 'Apple', 1]]);
	assert.deepEqual(inventoryChanges(before, after), ['-30 credits', '+1 Apple', '+2 Bread', '-1 Iron key']);
});

test('reports nothing when nothing changed or an inventory is missing', () => {
	const same = inventory(5, [[1, 'Bread', 2]]);
	assert.deepEqual(inventoryChanges(same, inventory(5, [[1, 'Bread', 2]])), []);
	assert.deepEqual(inventoryChanges(null, same), []);
});

test('shows unlimited amounts as ∞', () => {
	assert.equal(formatAmount(null), '∞');
	assert.equal(formatAmount(1200), (1200).toLocaleString());
});

test('turns the server changes into short lines', async () => {
	const { changeLines } = await import('../../resources/js/components/world/inventoryChanges.js');
	assert.deepEqual(changeLines({ credits: -5, items: [{ itemId: 1, name: 'Drink', delta: 1 }] }), ['-5 credits', '+1 Drink']);
	assert.deepEqual(changeLines(null), []);
});
