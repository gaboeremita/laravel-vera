import assert from 'node:assert/strict';
import { test } from 'node:test';
import { describeStep, executeAction } from '../../resources/js/components/world/residentActions.js';

const layout = { zones: [{ id: 'bar', entry: { x: 1, y: 0, z: 1 }, private: false, activities: [] }], objects: [] };

function fakeCommands(outcomes = {}) {
	const calls = [];
	const result = (name) => Promise.resolve(outcomes[name] ?? { outcome: 'completed', reason: null });
	return {
		calls,
		goTo: (point) => { calls.push(['goTo', point]); return result('goTo'); },
		pose: (name) => { calls.push(['pose', name]); return result('pose'); },
		hold: (ms) => { calls.push(['hold', ms]); return result('hold'); },
	};
}

test('a plan runs its steps in order and records each one', async () => {
	const commands = fakeCommands();
	const events = [];
	const result = await executeAction({
		verb: 'plan',
		target: 'get a drink',
		steps: [
			{ verb: 'go_to', target: 'bar' },
			{ verb: 'do', description: 'mixes a drink', pose: null },
			{ verb: 'pose', target: 'cheers' },
		],
	}, {
		commands,
		layout,
		onStepStart: (step, index, total) => { events.push(`start ${index + 1}/${total} ${describeStep(step)}`); return index; },
		onStepEnd: (step, index, stepResult, started) => { events.push(`end ${started} ${stepResult.outcome}`); },
	});

	assert.deepEqual(result, { outcome: 'completed', reason: null });
	assert.deepEqual(commands.calls.map(([name]) => name), ['goTo', 'hold', 'pose']);
	assert.deepEqual(events, ['start 1/3 go to bar', 'end 0 completed', 'start 2/3 mixes a drink', 'end 1 completed', 'start 3/3 cheers', 'end 2 completed']);
});

test('a plan stops at the first step that fails and says which one', async () => {
	const commands = fakeCommands({ goTo: { outcome: 'failed', reason: 'got stuck on the way' } });
	const result = await executeAction({
		verb: 'plan',
		target: 'get a drink',
		steps: [{ verb: 'go_to', target: 'bar' }, { verb: 'do', description: 'mixes a drink' }],
	}, { commands, layout });

	assert.deepEqual(result, { outcome: 'failed', reason: 'step 1 of 2 (go to bar): got stuck on the way' });
	assert.deepEqual(commands.calls.map(([name]) => name), ['goTo']);
});

test('a narrated step plays its pose, if any, and holds while her narration carries it', async () => {
	const commands = fakeCommands();
	await executeAction({ verb: 'do', description: 'sings a song', pose: 'sing' }, { commands, layout });

	assert.deepEqual(commands.calls.map(([name]) => name), ['pose', 'hold']);
});
