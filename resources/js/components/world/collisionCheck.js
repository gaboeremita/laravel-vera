import { Raycaster, Vector3 } from 'three';

// Sampled relative to an entity's ground Y — ankle, waist, chest — to
// approximate a vertical profile without full capsule-sweep collision.
const SAMPLE_HEIGHTS = [0.2, 0.9, 1.5];
const raycaster = new Raycaster();

// A wall's collision mesh bounding box (Box3.setFromObject) spans straight
// across any hole in it — a doorway is a gap in the mesh's actual geometry,
// but its axis-aligned bounding box still covers that gap entirely, so an
// AABB-vs-AABB check treats every open doorway as solid. Raycasting against
// the real triangles respects the actual opening instead of the mesh's
// bounding envelope.
export function isPathBlocked(fromX, fromZ, toX, toZ, groundY, meshes) {
	if (!meshes || meshes.length === 0) return false;

	const dx = toX - fromX;
	const dz = toZ - fromZ;
	const distance = Math.hypot(dx, dz);
	if (distance === 0) return false;

	const direction = new Vector3(dx / distance, 0, dz / distance);

	for (const height of SAMPLE_HEIGHTS) {
		raycaster.set(new Vector3(fromX, groundY + height, fromZ), direction);
		raycaster.far = distance + 0.1;
		if (raycaster.intersectObjects(meshes, false).length > 0) return true;
	}

	return false;
}
