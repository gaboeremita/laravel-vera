import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createPassageTrigger } from '../../resources/js/components/world/passageTrigger.js';

const door = { id: 'lobby-door', position: { x: 0, y: 0, z: 0 }, radius: 1 };
const pier = { id: 'pier', position: { x: 10, y: 0, z: 0 }, radius: 1 };
const at = (x, z = 0) => ({ x, y: 0, z });

test('does not fire while the player stands inside the passage on arrival', () => {
	const trigger = createPassageTrigger([door], new Set(['lobby-door']));

	assert.equal(trigger.update(at(0.5)), null);
	assert.equal(trigger.update(at(0.2)), null);
});

test('fires after the player leaves the passage and walks back in', () => {
	const trigger = createPassageTrigger([door], new Set(['lobby-door']));

	trigger.update(at(0.5));
	assert.equal(trigger.update(at(3)), null);
	assert.equal(trigger.update(at(0.5)), door);
	assert.equal(trigger.update(at(0.4)), null);
});

test('fires when walking into a passage from outside', () => {
	const trigger = createPassageTrigger([door], new Set(['lobby-door']));

	trigger.update(at(5));
	assert.equal(trigger.update(at(0.9)), door);
});

test('never fires for an unlinked passage', () => {
	const trigger = createPassageTrigger([door, pier], new Set(['lobby-door']));

	trigger.update(at(5));
	assert.equal(trigger.update(at(10)), null);
});

test('ignores a passage on another floor', () => {
	const trigger = createPassageTrigger([door], new Set(['lobby-door']));

	trigger.update({ x: 5, y: 4, z: 0 });
	assert.equal(trigger.update({ x: 0, y: 4, z: 0 }), null);
});
