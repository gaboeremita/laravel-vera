function resolveTarget(layout, id) {
	const zone = layout?.zones?.find((candidate) => candidate.id === id);
	if (zone) return { point: zone.entry, isPrivate: zone.private };

	const object = layout?.objects?.find((candidate) => candidate.id === id);
	if (!object) return null;
	const objectZone = layout.zones.find((candidate) => candidate.id === object.zoneId);
	return { point: object.spots[0]?.approach ?? object.position, near: true, isPrivate: Boolean(objectZone?.private) };
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
 */
export async function executeAction(action, { commands, layout, getFollowTarget, fromUser, residentId, occupiedSpots }) {
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
				position: spot.position,
				approach: spot.approach,
				facing: spot.facing,
				posture: activity.posture ?? 'standing',
				poseName: activity.pose ?? null,
				onLeave: release,
			});
			if (result.outcome !== 'completed') release();
			return result;
		}
		case 'zone': {
			const activity = layout?.zones?.flatMap((zone) => zone.activities).find((candidate) => candidate.id === action.activity);
			if (!activity) return { outcome: 'failed', reason: `there is no activity called "${action.activity}" here any more` };
			return commands.zone({ posture: activity.posture ?? 'standing', poseName: activity.pose ?? null });
		}
		case 'stay':
			return { outcome: 'completed', reason: null };
		default:
			return { outcome: 'failed', reason: 'that cannot be done yet' };
	}
}
