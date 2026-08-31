import { Ray, Vector3 } from 'three';

const MIN_WALKABLE_NORMAL_Y = Math.cos(Math.PI / 4);
const CONTACT_TOLERANCE = 0.001;
const ray = new Ray(new Vector3(), new Vector3(0, -1, 0));
const hit = new Vector3();
const normal = new Vector3();
const candidates = [];

export function getGroundHeight(x, z, octree, minY, maxY) {
	ray.origin.set(x, maxY + CONTACT_TOLERANCE, z);
	candidates.length = 0;
	octree.getRayTriangles(ray, candidates);
	let groundY = null;
	for (const triangle of candidates) {
		triangle.getNormal(normal);
		if (Math.abs(normal.y) < MIN_WALKABLE_NORMAL_Y) continue;
		// Render materials and triangle winding must not decide whether a floor is solid.
		if (!ray.intersectTriangle(triangle.a, triangle.b, triangle.c, false, hit)) continue;
		if (hit.y < minY - CONTACT_TOLERANCE || hit.y > maxY + CONTACT_TOLERANCE) continue;
		if (groundY === null || hit.y > groundY) groundY = hit.y;
	}
	candidates.length = 0;
	return groundY;
}
