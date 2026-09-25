import { RESTING_POSTURES } from './playerPostures.js';
import { hasRoomFor } from './spotOccupancy.js';

/** The nearest spot of the object offering the activity that has room for the user or is already theirs. */
export function nearestFreeSpot(object, activityId, occupiedSpots, foot) {
	let nearest = null;
	let nearestDistance = Infinity;
	for (const spot of object.spots) {
		if (!spot.activities.some((activity) => activity.id === activityId)) continue;
		if (!hasRoomFor(occupiedSpots, spot, 'user')) continue;
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
