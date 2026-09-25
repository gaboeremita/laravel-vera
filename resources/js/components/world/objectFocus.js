import { floorAt } from './worldLocation.js';

export const REACH = 2.5;
export const GAZE_ANGLE = Math.PI / 6;

export function reachPoints(object) {
	return [object.position, ...object.spots.map((spot) => spot.approach)];
}

function nearestReachPoint(object, foot) {
	let nearest = null;
	let nearestDistance = Infinity;
	for (const point of reachPoints(object)) {
		const distance = Math.hypot(point.x - foot.x, point.z - foot.z);
		if (distance < nearestDistance) {
			nearest = point;
			nearestDistance = distance;
		}
	}
	return { point: nearest, distance: nearestDistance };
}

function onFloor(layout, object, floorId) {
	return (floorAt(layout, object.position.y)?.id ?? null) === floorId;
}

export function isWithinReach(layout, object, foot, floorId) {
	return onFloor(layout, object, floorId) && nearestReachPoint(object, foot).distance <= REACH;
}

/**
 * The object in front of the user: the one looked at within reach, else the
 * nearest one within reach.
 */
export function pickFocus({ layout, foot, forward, floorId }) {
	const forwardLength = Math.hypot(forward.x, forward.z) || 1;
	let looked = null;
	let lookedAngle = GAZE_ANGLE;
	let nearest = null;
	let nearestDistance = Infinity;
	for (const object of layout?.objects ?? []) {
		if (!onFloor(layout, object, floorId)) continue;
		const { point, distance } = nearestReachPoint(object, foot);
		if (distance > REACH) continue;
		if (distance < nearestDistance) {
			nearest = object;
			nearestDistance = distance;
		}
		const dx = point.x - foot.x;
		const dz = point.z - foot.z;
		const angle = distance < 0.01 ? 0 : Math.acos(Math.max(-1, Math.min(1, (dx * forward.x + dz * forward.z) / (distance * forwardLength))));
		if (angle < lookedAngle) {
			looked = object;
			lookedAngle = angle;
		}
	}
	return looked ?? nearest;
}

/**
 * Where the object's label belongs: over its spot nearest the user. One object
 * can group furniture across a room (two benches either side of an atrium), so
 * its marker may be metres away from anything the user sees.
 */
export function anchorPoint(object, foot) {
	let nearest = object.position;
	let nearestDistance = Infinity;
	for (const spot of object.spots) {
		const distance = Math.hypot(spot.position.x - foot.x, spot.position.z - foot.z);
		if (distance < nearestDistance) {
			nearest = spot.position;
			nearestDistance = distance;
		}
	}
	return nearest;
}

export const COMPACT_OBJECT_RADIUS = 1.5;

/** Whether every spot sits close to the object's marker, so the marker stands where the object is seen. */
export function isCompact(object) {
	return object.spots.every((spot) => Math.hypot(spot.position.x - object.position.x, spot.position.z - object.position.z) <= COMPACT_OBJECT_RADIUS);
}

export function objectsWithin(layout, foot, floorId, radius) {
	return (layout?.objects ?? [])
		.filter((object) => onFloor(layout, object, floorId) && nearestReachPoint(object, foot).distance <= radius)
		.map((object) => object.id);
}

/**
 * How many spots offering an activity are free, and who holds the others.
 * `residentNames` maps holder ids to names; the user's own hold reads "YOU".
 */
export function spotAvailability(object, activityId, occupiedSpots, residentNames) {
	const spots = object.spots.filter((spot) => spot.activities.some((activity) => activity.id === activityId));
	const takenBy = [];
	let free = 0;
	for (const spot of spots) {
		const holder = occupiedSpots.get(spot.id);
		if (holder === undefined) free++;
		else takenBy.push(holder === 'user' ? 'YOU' : residentNames.get(holder) ?? 'SOMEONE');
	}
	return { free, total: spots.length, takenBy };
}
