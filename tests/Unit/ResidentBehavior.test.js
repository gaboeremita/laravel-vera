import assert from 'node:assert/strict';
import { test } from 'node:test';
import { parseBehaviorSettings } from '../../resources/js/components/world/behaviorSettings.js';
import { homeState } from '../../resources/js/components/world/residentHome.js';
import { nextStopIndex, routeAction, routePause } from '../../resources/js/components/world/residentRoutes.js';
import { idleWait } from '../../resources/js/components/world/residentMotion.js';
import { withinArea } from '../../resources/js/components/world/zoneAccess.js';
import { OUTSIDE_AREA, executeAction } from '../../resources/js/components/world/residentActions.js';

const zone = (id, parentId, outline) => ({ id, parentId, name: id, private: false, floorId: null, minY: -1, maxY: 3, outline, entry: { x: outline[0][0] + 1, y: 0, z: outline[0][1] + 1 }, activities: [] });
const layout = {
	floors: [],
	zones: [
		zone('fork-yard', null, [[0, 0], [10, 0], [10, 10], [0, 10]]),
		zone('fork-garage', 'fork-yard', [[6, 6], [10, 6], [10, 10], [6, 10]]),
		zone('pipe-street', null, [[-20, 0], [0, 0], [0, 10], [-20, 10]]),
	],
	objects: [{
		id: 'zombie-throne', name: 'Throne', zoneId: 'fork-garage', position: { x: 8, y: 0, z: 8 },
		spots: [{ id: 'zombie-throne-seat', position: { x: 8, y: 0.87, z: 8.5 }, approach: { x: 8, y: 0, z: 7.9 }, facing: Math.PI, capacity: 1, activities: [{ id: 'hold-court', name: 'Hold court', posture: 'sitting', pose: 'talk' }] }],
	}],
};

function fakeCommands() {
	const calls = [];
	const done = () => Promise.resolve({ outcome: 'completed', reason: null });
	return {
		calls,
		goTo: (point) => { calls.push(['goTo', point]); return done(); },
		follow: () => { calls.push(['follow']); return done(); },
		wander: (area) => { calls.push(['wander', area ?? null]); return done(); },
	};
}

test('behavior settings parse from the editor and say what is wrong', () => {
	assert.deepEqual(parseBehaviorSettings(''), { behaviorSettings: null, error: null });
	assert.deepEqual(parseBehaviorSettings('{"area":["fork-yard"],"decisionSeconds":{"min":30,"max":60}}').behaviorSettings, { area: ['fork-yard'], decisionSeconds: { min: 30, max: 60 } });
	assert.equal(parseBehaviorSettings('{"speed":2}').error, 'Unknown key "speed"; allowed: radius, homeSpot, route, area, decisionSeconds');
	assert.equal(parseBehaviorSettings('{"route":[{"target":"the-heap"}]}').error, '"route" must be a list of at least two stops');
	assert.equal(parseBehaviorSettings('{"route":[{"pause":5},{"target":"the-heap"}]}').error, 'Stop 1 needs a "target" or a "point"');
	assert.equal(parseBehaviorSettings('{"homeSpot":{"spotId":"zombie-throne-seat"}}').error, '"homeSpot" needs a "spotId" and an "activityId"');
	assert.equal(parseBehaviorSettings('{"decisionSeconds":{"min":60,"max":30}}').error, '"decisionSeconds" needs a "min" and a "max", in seconds');
	assert.equal(parseBehaviorSettings('{').error, 'Not valid JSON');
});

test('a resident with a home spot starts on it, in its posture, facing the way it faces', () => {
	const start = homeState({ behaviorSettings: { homeSpot: { spotId: 'zombie-throne-seat', activityId: 'hold-court' } } }, layout);
	assert.equal(start.spotId, 'zombie-throne-seat');
	assert.equal(start.activityId, 'hold-court');
	assert.equal(start.posture, 'sitting');
	assert.deepEqual(start.position, { x: 8, y: 0.87, z: 8.5 });
	assert.deepEqual(start.exitPosition, { x: 8, y: 0, z: 7.9 });
	assert.ok(Math.abs(start.rotation.y) < 1e-9);
	assert.equal(homeState({ behaviorSettings: { homeSpot: { spotId: 'gone', activityId: 'hold-court' } } }, layout), null);
	assert.equal(homeState({ behaviorSettings: { homeSpot: { spotId: 'zombie-throne-seat', activityId: 'dance' } } }, layout), null);
	assert.equal(homeState({ behaviorSettings: null }, layout), null);
});

test('a route walks to points, takes spots for their activity, walks to places, and loops', () => {
	assert.deepEqual(routeAction({ point: { x: 1, y: 0, z: 2 } }), { verb: 'go_to_point', point: { x: 1, y: 0, z: 2 } });
	assert.deepEqual(routeAction({ target: 'zombie-throne-seat', activity: 'hold-court' }), { verb: 'use', target: 'zombie-throne-seat', activity: 'hold-court' });
	assert.deepEqual(routeAction({ target: 'pipe-street' }), { verb: 'go_to', target: 'pipe-street' });
	assert.equal(routePause({ target: 'pipe-street', pause: 20 }), 20000);
	assert.equal(routePause({ target: 'pipe-street' }), 5000);
	assert.equal(nextStopIndex(0, [{}, {}, {}]), 1);
	assert.equal(nextStopIndex(2, [{}, {}, {}]), 0);
});

test('a resident with her own pace decides within it, wherever she is', () => {
	const pace = { min: 30, max: 60 };
	assert.equal(idleWait('standing', () => 0, pace), 30000);
	assert.equal(idleWait('sitting', () => 1, pace), 60000);
	assert.equal(idleWait('sitting', () => 0), 20000);
});

test('an area covers its zones and the zones inside them, and an empty area covers everything', () => {
	assert.equal(withinArea(layout, 'fork-garage', ['fork-yard']), true);
	assert.equal(withinArea(layout, 'pipe-street', ['fork-yard']), false);
	assert.equal(withinArea(layout, null, ['fork-yard']), false);
	assert.equal(withinArea(layout, 'pipe-street', []), true);
});

test('a resident who keeps to an area stays inside it on her own and follows the user only when told to', async () => {
	const context = (commands, fromUser = false) => ({ commands, layout, fromUser, area: ['fork-yard'], getFollowTarget: () => ({ x: -10, y: 0, z: 5 }) });

	const commands = fakeCommands();
	assert.deepEqual(await executeAction({ verb: 'go_to', target: 'pipe-street' }, context(commands)), { outcome: 'failed', reason: OUTSIDE_AREA });
	assert.deepEqual(await executeAction({ verb: 'go_to', target: 'user' }, context(commands)), { outcome: 'failed', reason: OUTSIDE_AREA });
	assert.deepEqual(await executeAction({ verb: 'follow' }, context(commands)), { outcome: 'failed', reason: OUTSIDE_AREA });
	await executeAction({ verb: 'go_to', target: 'fork-garage' }, context(commands));
	await executeAction({ verb: 'wander' }, context(commands));
	assert.deepEqual(commands.calls.map(([verb]) => verb), ['goTo', 'wander']);
	assert.deepEqual(commands.calls[1][1].outline, layout.zones[0].outline);

	const told = fakeCommands();
	await executeAction({ verb: 'follow' }, context(told, true));
	assert.deepEqual(told.calls, [['follow']]);
});
