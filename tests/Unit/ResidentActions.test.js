import assert from 'node:assert/strict';
import { test } from 'node:test';
import { describeStep, executeAction } from '../../resources/js/components/world/residentActions.js';
import { inTalkingReach } from '../../resources/js/components/world/talkingReach.js';

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

test('a narrated step plays its pose, or holds while her narration carries it', async () => {
	const withPose = fakeCommands();
	await executeAction({ verb: 'do', description: 'sings a song', pose: 'sing' }, { commands: withPose, layout });
	assert.deepEqual(withPose.calls.map(([name]) => name), ['pose']);

	const withoutPose = fakeCommands();
	await executeAction({ verb: 'do', description: 'makes tea' }, { commands: withoutPose, layout });
	assert.deepEqual(withoutPose.calls.map(([name]) => name), ['hold']);
});

function talkingCommands(position, { arriveAt = null } = {}) {
	const commands = fakeCommands();
	const state = { position };
	commands.state = () => state;
	commands.approach = (point) => {
		commands.calls.push(['approach', point]);
		state.position = arriveAt ? arriveAt(point) : { x: point.x - 1, y: point.y, z: point.z };
		return Promise.resolve({ outcome: 'completed', reason: null });
	};
	commands.inTalkingReach = (target) => inTalkingReach(state.position, target, () => true);
	commands.faceToward = (point) => { commands.calls.push(['faceToward', point]); };
	return commands;
}

const floors = { ...layout, floors: [{ id: 'ground', minY: -1, maxY: 3 }, { id: 'gallery', minY: 3, maxY: 7 }] };

test('talking to someone within reach speaks without walking over', async () => {
	const commands = talkingCommands({ x: 0, y: 0, z: 0 });
	const spoken = [];
	const result = await executeAction({ verb: 'talk_to', target: 'user', line: 'Hey.' }, {
		commands,
		layout,
		getFollowTarget: () => ({ x: 1, y: 0, z: 1 }),
		onSpeak: (action) => { spoken.push(action.line); },
	});

	assert.deepEqual(result, { outcome: 'completed', reason: null });
	assert.deepEqual(commands.calls, [['faceToward', { x: 1, y: 0, z: 1 }]]);
	assert.deepEqual(spoken, ['Hey.']);
});

test('talking to a resident across the room walks over first', async () => {
	const commands = talkingCommands({ x: 0, y: 0, z: 0 });
	const vera = { x: 8, y: 0, z: 0 };
	const result = await executeAction({ verb: 'talk_to', target: '7', line: 'Vera!' }, {
		commands,
		layout,
		getResidentPosition: (residentId) => (residentId === 7 ? vera : null),
		onSpeak: () => true,
	});

	assert.equal(result.outcome, 'completed');
	assert.deepEqual(commands.calls.map(([name]) => name), ['approach', 'faceToward']);
});

test('someone right above on another floor is not within reach', async () => {
	const commands = talkingCommands({ x: 0, y: 0, z: 0 }, { arriveAt: () => ({ x: 0, y: 0, z: 0 }) });
	const spoken = [];
	const result = await executeAction({ verb: 'talk_to', target: 'user', line: 'Up there?' }, {
		commands,
		layout: floors,
		getFollowTarget: () => ({ x: 0.5, y: 4, z: 0 }),
		onSpeak: (action) => { spoken.push(action.line); },
	});

	assert.deepEqual(result, { outcome: 'failed', reason: 'could not reach the user' });
	assert.deepEqual(spoken, []);
	assert.equal(commands.calls.filter(([name]) => name === 'approach').length, 3);
});

test('she follows someone who moved while she walked over', async () => {
	const commands = talkingCommands({ x: 0, y: 0, z: 0 });
	const spots = [{ x: 10, y: 0, z: 0 }, { x: 20, y: 0, z: 0 }, { x: 20, y: 0, z: 0 }];
	let seen = 0;
	const result = await executeAction({ verb: 'talk_to', target: 'user', line: 'Wait up.' }, {
		commands,
		layout,
		getFollowTarget: () => spots[Math.min(seen++, spots.length - 1)],
		onSpeak: () => true,
	});

	assert.equal(result.outcome, 'completed');
	assert.deepEqual(commands.calls.filter(([name]) => name === 'approach').map(([, point]) => point.x), [10, 20]);
});

test('talking fails when what she says cannot be delivered', async () => {
	const result = await executeAction({ verb: 'talk_to', target: 'user', line: 'Hey.' }, {
		commands: talkingCommands({ x: 0, y: 0, z: 0 }),
		layout,
		getFollowTarget: () => ({ x: 1, y: 0, z: 0 }),
		onSpeak: () => false,
	});

	assert.deepEqual(result, { outcome: 'failed', reason: 'could not start talking' });
});

test('talking to someone who cannot be found fails', async () => {
	const result = await executeAction({ verb: 'talk_to', target: '9', line: 'Hello?' }, { commands: talkingCommands({ x: 0, y: 0, z: 0 }), layout, getResidentPosition: () => null });

	assert.equal(result.outcome, 'failed');
});

test('she stays away while another resident has claimed the user', async () => {
	const commands = talkingCommands({ x: 0, y: 0, z: 0 });
	const result = await executeAction({ verb: 'talk_to', target: 'user', line: 'Hey.' }, {
		commands,
		layout,
		getFollowTarget: () => ({ x: 8, y: 0, z: 0 }),
		claimTarget: () => false,
		onSpeak: () => true,
	});

	assert.deepEqual(result, { outcome: 'failed', reason: 'the user is busy' });
	assert.deepEqual(commands.calls, []);
});

test('she lets go of the user when she cannot reach them', async () => {
	let released = 0;
	const result = await executeAction({ verb: 'talk_to', target: 'user', line: 'Hey.' }, {
		commands: talkingCommands({ x: 0, y: 0, z: 0 }, { arriveAt: () => ({ x: 0, y: 0, z: 0 }) }),
		layout,
		getFollowTarget: () => ({ x: 8, y: 0, z: 0 }),
		claimTarget: () => true,
		releaseTarget: () => { released++; },
		onSpeak: () => true,
	});

	assert.equal(result.outcome, 'failed');
	assert.equal(released, 1);
});

test('she keeps her claim on the user once she has spoken', async () => {
	let released = 0;
	const result = await executeAction({ verb: 'talk_to', target: 'user', line: 'Hey.' }, {
		commands: talkingCommands({ x: 0, y: 0, z: 0 }),
		layout,
		getFollowTarget: () => ({ x: 1, y: 0, z: 0 }),
		claimTarget: () => true,
		releaseTarget: () => { released++; },
		onSpeak: () => true,
	});

	assert.equal(result.outcome, 'completed');
	assert.equal(released, 0);
});

test('she leaves a resident alone who is already busy', async () => {
	const commands = talkingCommands({ x: 0, y: 0, z: 0 });
	const claimed = [];
	const result = await executeAction({ verb: 'talk_to', target: '7', line: 'Vera?' }, {
		commands,
		layout,
		getResidentPosition: () => ({ x: 8, y: 0, z: 0 }),
		claimTarget: (target) => { claimed.push(target); return false; },
		onSpeak: () => true,
	});

	assert.deepEqual(result, { outcome: 'failed', reason: 'they are busy' });
	assert.deepEqual(claimed, ['7']);
	assert.deepEqual(commands.calls, []);
});

test('she lets go of a resident once their conversation has started', async () => {
	const released = [];
	const result = await executeAction({ verb: 'talk_to', target: '7', line: 'Vera!' }, {
		commands: talkingCommands({ x: 0, y: 0, z: 0 }),
		layout,
		getResidentPosition: () => ({ x: 1, y: 0, z: 0 }),
		claimTarget: () => true,
		releaseTarget: (target) => { released.push(target); },
		onSpeak: () => true,
	});

	assert.equal(result.outcome, 'completed');
	assert.deepEqual(released, ['7']);
});

test('a resident who turns out busy on arrival fails with the reason given', async () => {
	const result = await executeAction({ verb: 'talk_to', target: '7', line: 'Vera!' }, {
		commands: talkingCommands({ x: 0, y: 0, z: 0 }),
		layout,
		getResidentPosition: () => ({ x: 1, y: 0, z: 0 }),
		onSpeak: () => 'they are busy',
	});

	assert.deepEqual(result, { outcome: 'failed', reason: 'they are busy' });
});
