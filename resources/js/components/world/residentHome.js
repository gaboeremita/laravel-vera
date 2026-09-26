import { facingAngleForMovement } from './residentMotion.js';

/**
 * Where a resident with a home spot starts a session she has no saved state
 * for: on that spot, in its activity's posture, facing the way the spot
 * faces. Null when she has no home spot or the world no longer has it.
 */
export function homeState(resident, layout) {
	const home = resident.behaviorSettings?.homeSpot;
	if (!home) return null;
	for (const object of layout?.objects ?? []) {
		const spot = object.spots.find((candidate) => candidate.id === home.spotId);
		if (!spot) continue;
		const activity = spot.activities.find((candidate) => candidate.id === home.activityId);
		if (!activity) return null;
		const facing = spot.facing ?? 0;
		return {
			position: spot.position,
			rotation: { x: 0, y: facingAngleForMovement(Math.sin(facing), Math.cos(facing)) ?? 0, z: 0 },
			spotId: spot.id,
			activityId: activity.id,
			posture: activity.posture ?? 'standing',
			exitPosition: spot.approach ?? null,
		};
	}
	return null;
}
