import { RESTING_POSTURES } from './playerPostures.js';

/** The nearest spot of the object offering the activity that is free or already the user's. */
export function nearestFreeSpot(object, activityId, occupiedSpots, foot) {
	let nearest = null;
	let nearestDistance = Infinity;
	for (const spot of object.spots) {
		if (!spot.activities.some((activity) => activity.id === activityId)) continue;
		const holder = occupiedSpots.get(spot.id);
		if (holder !== undefined && holder !== 'user') continue;
		const distance = Math.hypot(spot.approach.x - foot.x, spot.approach.z - foot.z);
		if (distance < nearestDistance) {
			nearest = spot;
			nearestDistance = distance;
		}
	}
	return nearest;
}

export function activityKind(activity, fromZone) {
	if (fromZone) return 'zone';
	return RESTING_POSTURES.includes(activity.posture) ? 'resting' : 'standing';
}
