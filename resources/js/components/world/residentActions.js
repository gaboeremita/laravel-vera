function resolveTarget(layout, id) {
	const zone = layout?.zones?.find((candidate) => candidate.id === id);
	if (zone) return { point: zone.entry, isPrivate: zone.private };

	const object = layout?.objects?.find((candidate) => candidate.id === id);
	if (!object) return null;
	const objectZone = layout.zones.find((candidate) => candidate.id === object.zoneId);
	return { point: object.spots[0]?.approach ?? object.position, isPrivate: Boolean(objectZone?.private) };
}

/**
 * Runs one action for a resident and resolves with its outcome:
 * { outcome: 'completed' | 'failed' | 'interrupted', reason }.
 */
export async function executeAction(action, { commands, layout, getFollowTarget, fromUser }) {
	if (action.verb === 'invalid') return { outcome: 'failed', reason: action.reason };
	if (!commands) return { outcome: 'failed', reason: 'you are not ready to move yet' };

	switch (action.verb) {
		case 'go_to': {
			const target = resolveTarget(layout, action.target);
			if (!target) return { outcome: 'failed', reason: `there is no place or thing called "${action.target}" here any more` };
			if (target.isPrivate && !fromUser) return { outcome: 'failed', reason: 'that is a private place' };
			return commands.goTo(target.point);
		}
		case 'follow':
			return commands.follow(getFollowTarget);
		case 'stop':
			return commands.stop();
		case 'stay':
			return { outcome: 'completed', reason: null };
		default:
			return { outcome: 'failed', reason: 'you cannot do that yet' };
	}
}
