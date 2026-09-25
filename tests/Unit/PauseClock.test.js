import assert from 'node:assert/strict';
import { test } from 'node:test';
import { setTimeout as delay } from 'node:timers/promises';
import { Clock } from 'three';
import { freezeClock, resumeClock } from '../../resources/js/components/world/pauseClock.js';

const wait = (ms) => delay(ms);

test('a frozen clock gives frames no time', async () => {
	const clock = new Clock();
	clock.getDelta();
	freezeClock(clock);
	const frozenAt = clock.elapsedTime;

	await wait(40);

	assert.equal(clock.getDelta(), 0);
	assert.equal(clock.elapsedTime, frozenAt);
});

test('resuming carries on from where the clock froze, without the paused time', async () => {
	const clock = new Clock();
	clock.getDelta();
	freezeClock(clock);
	const frozenAt = clock.elapsedTime;

	await wait(60);
	resumeClock(clock);

	assert.ok(clock.getDelta() < 0.02);
	assert.ok(clock.elapsedTime - frozenAt < 0.02);
	await wait(20);
	assert.ok(clock.getDelta() > 0);
});
