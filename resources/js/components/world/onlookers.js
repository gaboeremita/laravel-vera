const SIGHT_DISTANCE = 15;
const ALWAYS_NOTICED_DISTANCE = 4;
const FIELD_OF_VIEW_HALF_ANGLE = (110 * Math.PI) / 180;
const RESIDENT_EYE_HEIGHT = 1.5;

/**
 * The residents who could reasonably see the user: same floor, close enough,
 * nothing solid in between, and either very close or looking their way.
 * `residentPose(id)` returns `{ position, yaw }` with `yaw` the avatar's own
 * rotation, whose front points down its local -Z.
 */
export function selectOnlookers({ residents, userEye, userFloorId, floorOf, residentPose, hasLineOfSight }) {
	return residents.filter((resident) => {
		const pose = residentPose(resident.id);
		if (!pose) return false;
		const { position, yaw } = pose;
		if (floorOf(position.y) !== userFloorId) return false;
		const dx = userEye.x - position.x;
		const dz = userEye.z - position.z;
		const distance = Math.hypot(dx, dz);
		if (distance > SIGHT_DISTANCE) return false;
		if (distance > ALWAYS_NOTICED_DISTANCE) {
			const facingX = -Math.sin(yaw);
			const facingZ = -Math.cos(yaw);
			const angle = Math.acos(Math.max(-1, Math.min(1, (facingX * dx + facingZ * dz) / distance)));
			if (angle > FIELD_OF_VIEW_HALF_ANGLE) return false;
		}
		return hasLineOfSight({ x: position.x, y: position.y + RESIDENT_EYE_HEIGHT, z: position.z }, userEye);
	});
}
