import { claimSpot, releaseSpot } from './spotOccupancy.js';
import { floorAt } from './worldLocation.js';

function resolveTarget(layout, id) {
	const zone = layout?.zones?.find((candidate) => candidate.id === id);
	if (zone) return { point: zone.entry, isPrivate: zone.private };

	const object = layout?.objects?.find((candidate) => candidate.id === id);
	if (!object) return null;
	const objectZone = layout.zones.find((candidate) => candidate.id === object.zoneId);
	return { point: object.spots[0]?.approach ?? object.position, near: true, isPrivate: Boolean(objectZone?.private) };
}

const DO_HOLD_MS = 6000;

/**
 * Why talking to someone did not happen when they were already busy: an
 * ordinary outcome she learns from, not a failure worth reporting.
 */
export const USER_BUSY = 'the user is busy';
export const RESIDENT_BUSY = 'they are busy';

/** Walks toward someone who keeps moving before she gives up on reaching them. */
const TALK_ATTEMPTS = 3;

/** A step of a plan in a few words, for thought bubbles and failure reasons. */
export function describeStep(step) {
	switch (step.verb) {
		case 'go_to': return `go to ${step.target}`;
		case 'use': return `${step.activity} at ${step.target}`;
		case 'zone': return step.activity;
		case 'pose': return step.target;
		case 'do': return step.description;
		case 'swim_to_edge': return 'swim to the edge';
		case 'wander': return step.target ? `wander around ${step.target}` : 'wander around';
		case 'talk_to': return step.target === 'user' ? 'talk to the user' : 'talk to someone';
		default: return step.verb;
	}
}

function findSpot(layout, spotId) {
	for (const object of layout?.objects ?? []) {
		const spot = object.spots.find((candidate) => candidate.id === spotId);
		if (spot) return { object, spot };
	}
	return null;
}

async function talkTo(action, toUser, { commands, layout, getFollowTarget, getResidentPosition, onSpeak }) {
	const locate = () => (toUser ? getFollowTarget?.() : getResidentPosition?.(Number(action.target))) ?? null;
	const within = (target) => {
		const own = commands.state().position;
		return (floorAt(layout, own.y)?.id ?? null) === (floorAt(layout, target.y)?.id ?? null) && commands.inTalkingReach(target);
	};
	let target = locate();
	for (let attempt = 0; target && !within(target) && attempt < TALK_ATTEMPTS; attempt++) {
		const arrival = await commands.approach(target);
		if (arrival.outcome !== 'completed') return arrival;
		target = locate();
	}
	if (!target || !within(target)) return { outcome: 'failed', reason: toUser ? 'could not reach the user' : 'could not reach them' };
	commands.faceToward(target);
	const spoke = await onSpeak?.(action);
	if (spoke === false) return { outcome: 'failed', reason: 'could not start talking' };
	if (typeof spoke === 'string') return { outcome: 'failed', reason: spoke };
	return { outcome: 'completed', reason: null };
}

/**
 * Runs one action for a resident and resolves with its outcome:
 * { outcome: 'completed' | 'failed' | 'interrupted', reason }.
 * A plan runs its steps in order, calling onStepStart(step, index, total)
 * and onStepEnd(step, index, result, started) around each, and stops at the
 * first step that does not complete.
 */
export async function executeAction(action, context) {
	const { commands, layout, getFollowTarget, fromUser, residentId, occupiedSpots, claimTarget, releaseTarget, onStepStart, onStepEnd } = context;
	if (!commands) return { outcome: 'failed', reason: 'not ready to move yet' };

	switch (action.verb) {
		case 'go_to': {
			if (action.target === 'user') {
				const user = getFollowTarget?.();
				if (!user) return { outcome: 'failed', reason: 'could not find the user' };
				return commands.goTo(user, { near: true, towardUser: true });
			}
			const target = resolveTarget(layout, action.target);
			if (!target) return { outcome: 'failed', reason: `there is no place or thing called "${action.target}" here any more` };
			if (target.isPrivate && !fromUser) return { outcome: 'failed', reason: 'that is a private place' };
			return commands.goTo(target.point, { near: Boolean(target.near) });
		}
		case 'follow':
			return commands.follow(getFollowTarget);
		case 'swim_to_edge':
			return commands.swimToEdge();
		case 'wander': {
			if (!action.target) return commands.wander();
			const zone = layout?.zones?.find((candidate) => candidate.id === action.target);
			if (!zone) return { outcome: 'failed', reason: `there is no place called "${action.target}" here any more` };
			if (zone.private && !fromUser) return { outcome: 'failed', reason: 'that is a private place' };
			return commands.wander({ outline: zone.outline, y: zone.entry.y });
		}
		case 'stop':
			return commands.stop();
		case 'use': {
			const found = findSpot(layout, action.target);
			if (!found) return { outcome: 'failed', reason: `there is no spot called "${action.target}" here any more` };
			const { object, spot } = found;
			const activity = spot.activities.find((candidate) => candidate.id === action.activity);
			if (!activity) return { outcome: 'failed', reason: `"${action.activity}" cannot be done at ${spot.id}` };
			const objectZone = layout.zones.find((candidate) => candidate.id === object.zoneId);
			if (objectZone?.private && !fromUser) return { outcome: 'failed', reason: 'that is a private place' };
			if (!claimSpot(occupiedSpots, spot, residentId)) return { outcome: 'failed', reason: 'spot taken' };
			const release = () => releaseSpot(occupiedSpots, spot.id, residentId);
			const result = await commands.use({
				spotId: spot.id,
				activityId: activity.id,
				position: spot.position,
				approach: spot.approach,
				facing: spot.facing,
				posture: activity.posture ?? 'standing',
				poseName: action.pose !== undefined ? action.pose : activity.pose ?? null,
				onLeave: release,
			});
			if (result.outcome !== 'completed') release();
			return result;
		}
		case 'zone': {
			const activity = layout?.zones?.flatMap((zone) => zone.activities).find((candidate) => candidate.id === action.activity);
			if (!activity) return { outcome: 'failed', reason: `there is no activity called "${action.activity}" here any more` };
			return commands.zone({ activityId: activity.id, posture: activity.posture ?? 'standing', poseName: action.pose !== undefined ? action.pose : activity.pose ?? null });
		}
		case 'pose':
			return commands.pose(action.target);
		case 'do':
			return action.pose ? commands.pose(action.pose) : commands.hold(DO_HOLD_MS);
		case 'plan': {
			const steps = action.steps ?? [];
			for (const [index, step] of steps.entries()) {
				const started = await onStepStart?.(step, index, steps.length);
				const result = await executeAction(step, { ...context, onStepStart: null, onStepEnd: null });
				await onStepEnd?.(step, index, result, started);
				if (result.outcome !== 'completed') {
					return { outcome: result.outcome, reason: `step ${index + 1} of ${steps.length} (${describeStep(step)}): ${result.reason}` };
				}
			}
			return { outcome: 'completed', reason: null };
		}
		case 'talk_to': {
			const toUser = action.target === 'user';
			// Only one resident at a time can be on her way to talk to someone.
			// A claim on a resident lasts until their conversation starts; on
			// the user, until they answer or the invite runs out.
			if (claimTarget && !claimTarget(action.target)) return { outcome: 'failed', reason: toUser ? USER_BUSY : RESIDENT_BUSY };
			const result = await talkTo(action, toUser, context);
			if (!toUser || result.outcome !== 'completed') releaseTarget?.(action.target);
			return result;
		}
		case 'stay':
		case 'think':
		case 'remember':
			return { outcome: 'completed', reason: null };
		default:
			return { outcome: 'failed', reason: 'that cannot be done yet' };
	}
}
