import { Box3, Matrix4, Ray, Triangle, Vector3 } from 'three';
import { Octree } from 'three/addons/math/Octree.js';
import { getGroundHeight } from './groundHeight.js';
import { clampToBounds } from './clampToBounds.js';

export const CHARACTER_HEIGHT = 1.8;
export const PLAYER_EYE_HEIGHT = 1.6;
export const MAX_MOVEMENT_DELTA = 0.1;
export const CHARACTER_RADIUS = 0.25;
export const MAX_STEP_HEIGHT = 0.25;
export const MAX_DROP_HEIGHT = 0.35;
export const SWIM_DEPTH = 1.1;
export const LEAVE_WATER_DEPTH = 0.9;
export const MAX_FALL_HEIGHT = 2;
const AIR_GROUND_SEARCH = 3.5;
const MOVEMENT_STEP = 0.08;
const CONTACT_MARGIN = 0.005;
const SPAWN_SPACING = 0.6;
const MAX_SPAWN_RINGS = 40;
const INSIDE_PROBES = [new Vector3(1, 0, 0), new Vector3(-1, 0, 0), new Vector3(0, 0, 1), new Vector3(0, 0, -1)];
const COLLISION_NAME = /collision/i;
const WATER_SEARCH_HEIGHT = 4;

export class WorldCollision {
	constructor(scene) {
		this.octree = new Octree();
		this.waterOctree = new Octree();
		this.hasWater = false;
		this.bounds = new Box3();
		this.bodyBounds = new Box3();
		this.candidates = [];
		this.destination = new Vector3();
		this.triangleCount = 0;
		scene.updateWorldMatrix(true, true);

		const instanceMatrix = new Matrix4();
		const worldMatrix = new Matrix4();
		const collect = (node, parentVisible, parentCollider, parentPassable) => {
			const collider = parentCollider || COLLISION_NAME.test(node.name);
			const visible = parentVisible && node.visible;
			const passable = parentPassable || node.userData.passable === true;
			if (node.isMesh && passable && visible) {
				this.addGeometry(node.geometry, node.matrixWorld, this.waterOctree);
				this.hasWater = true;
			} else if (node.isMesh && !passable && (visible || collider)) {
				if (node.isInstancedMesh) {
					for (let instance = 0; instance < node.count; instance++) {
						node.getMatrixAt(instance, instanceMatrix);
						worldMatrix.multiplyMatrices(node.matrixWorld, instanceMatrix);
						this.addGeometry(node.geometry, worldMatrix);
					}
				} else {
					this.addGeometry(node.geometry, node.matrixWorld);
				}
			}
			for (const child of node.children) collect(child, visible, collider, passable);
			if (collider) node.visible = false;
		};
		collect(scene, true, false, false);
		if (this.triangleCount === 0) throw new Error('This environment has no geometry for collision.');
		this.octree.build();
		if (this.hasWater) this.waterOctree.build();
	}

	addGeometry(geometry, matrix, octree = this.octree) {
		const positions = geometry.getAttribute('position');
		if (!positions) return;
		const indices = geometry.index;
		const start = geometry.drawRange.start;
		const end = Math.min(indices?.count ?? positions.count, start + geometry.drawRange.count);
		for (let offset = start; offset + 2 < end; offset += 3) {
			const vertices = [0, 1, 2].map((corner) => new Vector3()
				.fromBufferAttribute(positions, indices ? indices.getX(offset + corner) : offset + corner)
				.applyMatrix4(matrix));
			const triangle = new Triangle(...vertices);
			if (triangle.getArea() === 0) continue;
			octree.addTriangle(triangle);
			if (octree !== this.octree) continue;
			for (const vertex of vertices) this.bounds.expandByPoint(vertex);
			this.triangleCount++;
		}
	}

	isBodyBlocked(from, to = from, radius = CHARACTER_RADIUS) {
		this.bodyBounds.min.set(
			Math.min(from.x, to.x) - radius,
			Math.min(from.y, to.y) + MAX_STEP_HEIGHT + CONTACT_MARGIN,
			Math.min(from.z, to.z) - radius,
		);
		this.bodyBounds.max.set(
			Math.max(from.x, to.x) + radius,
			Math.max(from.y, to.y) + CHARACTER_HEIGHT,
			Math.max(from.z, to.z) + radius,
		);
		this.candidates.length = 0;
		this.octree.getBoxTriangles(this.bodyBounds, this.candidates);
		return this.candidates.some((triangle) => this.bodyBounds.intersectsTriangle(triangle));
	}

	tryStep(position, dx, dz, canFall = false) {
		const clamped = clampToBounds(position.x + dx, position.y, position.z + dz, this.bounds);
		this.destination.set(clamped.x, position.y, clamped.z);
		// Check at the current foot height before probing a higher surface.
		if (this.isBodyBlocked(position, this.destination)) return false;
		const groundY = getGroundHeight(
			clamped.x, clamped.z, this.octree,
			position.y - MAX_DROP_HEIGHT, position.y + MAX_STEP_HEIGHT,
		);
		if (groundY === null) {
			if (!canFall) return false;
			const landingY = getGroundHeight(clamped.x, clamped.z, this.octree, position.y - MAX_FALL_HEIGHT, position.y - MAX_DROP_HEIGHT);
			if (landingY === null) return false;
			position.copy(this.destination);
			return 'fall';
		}
		this.destination.y = groundY;
		if (this.isBodyBlocked(position, this.destination)) return false;
		position.copy(this.destination);
		return true;
	}

	/**
	 * Walks along the ground. With `canFall`, stepping off a ledge up to
	 * MAX_FALL_HEIGHT high is allowed and reported as 'falling'; otherwise
	 * ledges block like walls.
	 */
	move(position, dx, dz, { canFall = false } = {}) {
		const steps = Math.ceil(Math.hypot(dx, dz) / MOVEMENT_STEP);
		if (steps === 0) return 'grounded';
		const stepX = dx / steps;
		const stepZ = dz / steps;
		for (let step = 0; step < steps; step++) {
			const moved = this.tryStep(position, stepX, stepZ, canFall);
			if (moved === 'fall') return 'falling';
			if (moved) continue;
			if (stepX === 0 || stepZ === 0) break;
			const movedX = this.tryStep(position, stepX, 0, canFall);
			if (movedX === 'fall') return 'falling';
			const movedZ = this.tryStep(position, 0, stepZ, canFall);
			if (movedZ === 'fall') return 'falling';
			if (!movedX && !movedZ) break;
		}
		return 'grounded';
	}

	/**
	 * Moves a body through the air for one frame. `velocity.y` is changed in
	 * place: zeroed when the head bumps something. Horizontal movement stops
	 * at walls and wherever there is no ground within reach below, so a jump
	 * can never carry the body off the edge of the world. Returns whether it
	 * landed.
	 */
	airStep(position, velocity, seconds) {
		const dx = velocity.x * seconds;
		const dz = velocity.z * seconds;
		const steps = Math.max(1, Math.ceil(Math.hypot(dx, dz) / MOVEMENT_STEP));
		for (let step = 0; step < steps; step++) {
			const clamped = clampToBounds(position.x + dx / steps, position.y, position.z + dz / steps, this.bounds);
			this.destination.set(clamped.x, position.y, clamped.z);
			if (this.isBodyBlocked(position, this.destination)) break;
			const groundBelow = getGroundHeight(clamped.x, clamped.z, this.octree, position.y - AIR_GROUND_SEARCH, position.y + MAX_STEP_HEIGHT);
			if (groundBelow === null) break;
			position.x = clamped.x;
			position.z = clamped.z;
		}

		const nextY = position.y + velocity.y * seconds;
		if (velocity.y > 0) {
			this.destination.set(position.x, nextY, position.z);
			if (this.isBodyBlocked(position, this.destination)) {
				velocity.y = 0;
				return { landed: false };
			}
			position.y = nextY;
			return { landed: false };
		}

		const groundY = getGroundHeight(position.x, position.z, this.octree, nextY, position.y + MAX_STEP_HEIGHT);
		if (groundY !== null) {
			position.y = groundY;
			return { landed: true };
		}
		position.y = nextY;
		return { landed: false };
	}

	findSpawn(preferred) {
		const origin = clampToBounds(preferred.x, preferred.y, preferred.z, this.bounds);
		const maxHeadroomY = Math.max(origin.y + MAX_STEP_HEIGHT, this.bounds.max.y - CHARACTER_HEIGHT);
		const heights = [Math.min(origin.y + MAX_STEP_HEIGHT, maxHeadroomY), maxHeadroomY];
		const position = new Vector3();
		for (const maxY of heights) {
			for (let ring = 0; ring <= MAX_SPAWN_RINGS; ring++) {
				for (let x = -ring; x <= ring; x++) {
					for (let z = -ring; z <= ring; z++) {
						if (Math.max(Math.abs(x), Math.abs(z)) !== ring) continue;
						position.set(origin.x + x * SPAWN_SPACING, origin.y, origin.z + z * SPAWN_SPACING);
						if (!this.bounds.containsPoint(position)) continue;
						const groundY = getGroundHeight(position.x, position.z, this.octree, this.bounds.min.y, maxY);
						if (groundY === null) continue;
						position.y = groundY;
						if (!this.isBodyStuck(position)) return position;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Where a body stuck inside geometry can stand instead: the nearest
	 * walkable navigation point, or the nearest open ground inside the bounds.
	 * Null when the body is already clear or there is nowhere to go.
	 */
	freeBodyPosition(position, navigation = null) {
		if (!this.isBodyStuck(position)) return null;
		const walkable = navigation?.nearestPoint(position);
		if (walkable && this.bounds.containsPoint(walkable) && !this.isBodyStuck(walkable)) return walkable;
		return this.findSpawn(position);
	}

	/**
	 * Whether a body overlaps geometry or stands wholly inside a solid, where
	 * it touches no face: rays out from its middle meet only the inside of
	 * surfaces, at least two of them.
	 */
	isBodyStuck(position) {
		if (this.isBodyBlocked(position)) return true;
		const middle = new Vector3(position.x, position.y + CHARACTER_HEIGHT / 2, position.z);
		const facings = INSIDE_PROBES
			.map((direction) => [direction, this.nearestTriangleAlong(new Ray(middle, direction))])
			.filter(([, triangle]) => triangle !== null)
			.map(([direction, triangle]) => triangle.getNormal(new Vector3()).dot(direction));
		return facings.length >= 2 && facings.every((facing) => facing > 0);
	}

	/** The first triangle a ray meets, facing either way. */
	nearestTriangleAlong(ray) {
		const triangles = [];
		this.octree.getRayTriangles(ray, triangles);
		const point = new Vector3();
		let nearest = null;
		let nearestDistance = Infinity;
		for (const triangle of triangles) {
			if (!ray.intersectTriangle(triangle.a, triangle.b, triangle.c, false, point)) continue;
			const distance = point.distanceTo(ray.origin);
			if (distance < nearestDistance) {
				nearest = triangle;
				nearestDistance = distance;
			}
		}
		return nearest;
	}

	hasLineOfSight(from, to) {
		const origin = new Vector3(from.x, from.y, from.z);
		const direction = new Vector3(to.x - from.x, to.y - from.y, to.z - from.z);
		const distance = direction.length();
		if (distance === 0) return true;
		const hit = this.octree.rayIntersect(new Ray(origin, direction.normalize()));
		return !hit || hit.distance >= distance;
	}

	/** Height of the water surface over a point standing at `groundY`, or null when it is dry. */
	waterSurfaceAbove(x, z, groundY) {
		if (!this.hasWater) return null;
		return getGroundHeight(x, z, this.waterOctree, groundY, groundY + WATER_SEARCH_HEIGHT);
	}

	restorePlayerPosition(savedPosition, fallback) {
		const coordinates = ['x', 'y', 'z'].map((axis) => savedPosition?.[axis]);
		if (!coordinates.every((coordinate) => typeof coordinate === 'number' && Number.isFinite(coordinate))) return fallback.clone();
		const preferred = new Vector3(coordinates[0], coordinates[1] - PLAYER_EYE_HEIGHT, coordinates[2]);
		return this.findSpawn(preferred) ?? fallback.clone();
	}

	dispose() {
		this.octree.clear();
		this.waterOctree.clear();
		this.candidates.length = 0;
	}
}
