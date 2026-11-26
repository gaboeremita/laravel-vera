export const INTERACTION_DISTANCE = 4;
export const INTERACTION_HEIGHT_DIFFERENCE = 2.5;

function interactionDistance(foot, position) {
	if (!position || Math.abs(foot.y - position.y) > INTERACTION_HEIGHT_DIFFERENCE) return null;
	const distance = Math.hypot(foot.x - position.x, foot.z - position.z);
	return distance <= INTERACTION_DISTANCE ? distance : null;
}

/**
 * Who the user would chat with: the resident they picked while that resident
 * is within reach, otherwise the nearest resident within reach, or null.
 */
export function chooseTalkTarget(residents, positions, foot, pickedResidentId = null) {
	let nearest = null;
	let nearestDistance = Infinity;
	for (const resident of residents) {
		const distance = interactionDistance(foot, positions.get(resident.id));
		if (distance === null) continue;
		if (resident.id === pickedResidentId) return resident;
		if (distance < nearestDistance) {
			nearest = resident;
			nearestDistance = distance;
		}
	}
	return nearest;
}
