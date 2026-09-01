import { Box3, Matrix4, Triangle, Vector3 } from 'three';
import { Octree } from 'three/addons/math/Octree.js';
import { getGroundHeight } from './groundHeight.js';
import { clampToBounds } from './clampToBounds.js';

export const CHARACTER_HEIGHT = 1.8;
export const PLAYER_EYE_HEIGHT = 1.6;
export const MAX_MOVEMENT_DELTA = 0.1;
const CHARACTER_RADIUS = 0.25;
const MAX_STEP_HEIGHT = 0.25;
const MAX_DROP_HEIGHT = 0.35;
const MOVEMENT_STEP = 0.08;
const CONTACT_MARGIN = 0.005;
const SPAWN_SPACING = 0.6;
const MAX_SPAWN_RINGS = 40;
const COLLISION_NAME = /collision/i;

export class WorldCollision {
	constructor(scene) {
		this.octree = new Octree();
		this.bounds = new Box3();
		this.bodyBounds = new Box3();
		this.candidates = [];
		this.destination = new Vector3();
		this.triangleCount = 0;
		scene.updateWorldMatrix(true, true);

		const instanceMatrix = new Matrix4();
		const worldMatrix = new Matrix4();
		const collect = (node, parentVisible, parentCollider) => {
			const collider = parentCollider || COLLISION_NAME.test(node.name);
			const visible = parentVisible && node.visible;
			if (node.isMesh && (visible || collider)) {
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
			for (const child of node.children) collect(child, visible, collider);
			if (collider) node.visible = false;
		};
		collect(scene, true, false);
		if (this.triangleCount === 0) throw new Error('This environment has no geometry for collision.');
		this.octree.build();
	}

	addGeometry(geometry, matrix) {
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
			this.octree.addTriangle(triangle);
			for (const vertex of vertices) this.bounds.expandByPoint(vertex);
			this.triangleCount++;
		}
	}

	isBodyBlocked(from, to = from) {
		this.bodyBounds.min.set(
			Math.min(from.x, to.x) - CHARACTER_RADIUS,
			Math.min(from.y, to.y) + MAX_STEP_HEIGHT + CONTACT_MARGIN,
			Math.min(from.z, to.z) - CHARACTER_RADIUS,
		);
		this.bodyBounds.max.set(
			Math.max(from.x, to.x) + CHARACTER_RADIUS,
			Math.max(from.y, to.y) + CHARACTER_HEIGHT,
			Math.max(from.z, to.z) + CHARACTER_RADIUS,
		);
		this.candidates.length = 0;
		this.octree.getBoxTriangles(this.bodyBounds, this.candidates);
		return this.candidates.some((triangle) => this.bodyBounds.intersectsTriangle(triangle));
	}

	tryStep(position, dx, dz) {
		const clamped = clampToBounds(position.x + dx, position.y, position.z + dz, this.bounds);
		this.destination.set(clamped.x, position.y, clamped.z);
		// Check at the current foot height before probing a higher surface.
		if (this.isBodyBlocked(position, this.destination)) return false;
		const groundY = getGroundHeight(
			clamped.x, clamped.z, this.octree,
			position.y - MAX_DROP_HEIGHT, position.y + MAX_STEP_HEIGHT,
		);
		if (groundY === null) return false;
		this.destination.y = groundY;
		if (this.isBodyBlocked(position, this.destination)) return false;
		position.copy(this.destination);
		return true;
	}

	move(position, dx, dz) {
		const steps = Math.ceil(Math.hypot(dx, dz) / MOVEMENT_STEP);
		if (steps === 0) return position;
		const stepX = dx / steps;
		const stepZ = dz / steps;
		for (let step = 0; step < steps; step++) {
			if (this.tryStep(position, stepX, stepZ)) continue;
			if (stepX === 0 || stepZ === 0) break;
			const movedX = this.tryStep(position, stepX, 0);
			const movedZ = this.tryStep(position, 0, stepZ);
			if (!movedX && !movedZ) break;
		}
		return position;
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
						if (!this.isBodyBlocked(position)) return position;
					}
				}
			}
		}
		return null;
	}

	restorePlayerPosition(savedPosition, fallback) {
		const coordinates = ['x', 'y', 'z'].map((axis) => savedPosition?.[axis]);
		if (!coordinates.every((coordinate) => typeof coordinate === 'number' && Number.isFinite(coordinate))) return fallback.clone();
		const preferred = new Vector3(coordinates[0], coordinates[1] - PLAYER_EYE_HEIGHT, coordinates[2]);
		return this.findSpawn(preferred) ?? fallback.clone();
	}

	dispose() {
		this.octree.clear();
		this.candidates.length = 0;
	}
}
