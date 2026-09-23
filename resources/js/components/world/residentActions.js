function resolveTarget(layout, id) {
	const zone = layout?.zones?.find((candidate) => candidate.id === id);
	if (zone) return { point: zone.entry, isPrivate: zone.private };

	const object = layout?.objects?.find((candidate) => candidate.id === id);
	if (!object) return null;
	const objectZone = layout.zones.find((candidate) => candidate.id === object.zoneId);
	return { point: object.spots[0]?.approach ?? object.position, near: true, isPrivate: Boolean(objectZone?.private) };
}

const DO_HOLD_MS = 6000;

/** A step of a plan in a few words, for thought bubbles and failure reasons. */
export function describeStep(step) {
	switch (step.verb) {
		case 'go_to': return `go to ${step.target}`;
		case 'use': return `${step.activity} at ${step.target}`;
		case 'zone': return step.activity;
		case 'pose': return step.target;
		case 'do': return step.description;
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

/**
 * Runs one action for a resident and resolves with its outcome:
 * { outcome: 'completed' | 'failed' | 'interrupted', reason }.
 * A plan runs its steps in order, calling onStepStart(step, index, total)
 * and onStepEnd(step, index, result, started) around each, and stops at the
 * first step that does not complete.
 */
export async function executeAction(action, context) {
	const { commands, layout, getFollowTarget, fromUser, residentId, occupiedSpots, onStepStart, onStepEnd } = context;
	if (!commands) return { outcome: 'failed', reason: 'not ready to move yet' };

	switch (action.verb) {
		case 'go_to': {
			const target = resolveTarget(layout, action.target);
			if (!target) return { outcome: 'failed', reason: `there is no place or thing called "${action.target}" here any more` };
			if (target.isPrivate && !fromUser) return { outcome: 'failed', reason: 'that is a private place' };
			return commands.goTo(target.point, { near: Boolean(target.near) });
		}
		case 'follow':
			return commands.follow(getFollowTarget);
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
			const holder = occupiedSpots.get(spot.id);
			if (holder !== undefined && holder !== residentId) return { outcome: 'failed', reason: 'spot taken' };

			occupiedSpots.set(spot.id, residentId);
			const release = () => {
				if (occupiedSpots.get(spot.id) === residentId) occupiedSpots.delete(spot.id);
			};
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
			return commands.zone({ posture: activity.posture ?? 'standing', poseName: action.pose !== undefined ? action.pose : activity.pose ?? null });
		}
		case 'pose':
			return commands.pose(action.target);
		case 'do':
			if (action.pose) await commands.pose(action.pose);
			return commands.hold(DO_HOLD_MS);
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
		case 'stay':
			return { outcome: 'completed', reason: null };
		default:
			return { outcome: 'failed', reason: 'that cannot be done yet' };
	}
}
