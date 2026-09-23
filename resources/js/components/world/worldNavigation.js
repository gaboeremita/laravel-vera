import { Ray, Vector3 } from 'three';
import { CHARACTER_RADIUS, MAX_DROP_HEIGHT, MAX_STEP_HEIGHT } from './collisionCheck.js';
import { getGroundHeight } from './groundHeight.js';

const CELL_SIZE = 0.4;
const LEVELS = 3;
const MAX_SURFACES = 8;
const MIN_WALKABLE_NORMAL_Y = Math.cos(Math.PI / 4);
const SURFACE_MERGE_DISTANCE = 0.05;
const NEAREST_SEARCH_RINGS = 3;
const NEAREST_MAX_HEIGHT_DIFFERENCE = 1;
const APPROACH_RADIUS = 1.2;
const LEVEL_TOLERANCE = 0.05;
const DROP_SEARCH_DEPTH = 3;
const WALL_MARGIN = 0.2;
const WALL_PENALTY = 0.8;
const SMOOTHING_MARGIN = WALL_MARGIN;
const MAX_EXPANSIONS = 40000;
const SMOOTHING_LOOKAHEAD = 25;
const SIGHT_SAMPLE_SPACING = 0.25;
const DIRECTIONS = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [1, -1], [-1, 1], [-1, -1]];
const DIAGONAL_PARTS = { 4: [0, 2], 5: [0, 3], 6: [1, 2], 7: [1, 3] };
const REVERSE_DIRECTION = [1, 0, 3, 2, 7, 6, 5, 4];
const EDGE_UNKNOWN = 0;
const EDGE_OPEN = 1;
const EDGE_BLOCKED = 2;

/**
 * Walkable grid over the collision world: every column is probed with one
 * downward ray, and each surface a body fits on becomes a node. Links between
 * nodes are checked only when a route search needs them, then cached.
 */
class NavigationGrid {
	constructor(collisionWorld, { bounds, minY, maxY, cellSize = CELL_SIZE }) {
		this.collisionWorld = collisionWorld;
		this.cellSize = cellSize;
		this.minX = bounds.minX;
		this.minZ = bounds.minZ;
		this.minY = minY;
		this.maxY = maxY;
		this.columns = Math.max(1, Math.ceil((bounds.maxX - bounds.minX) / cellSize) + 1);
		this.rows = Math.max(1, Math.ceil((bounds.maxZ - bounds.minZ) / cellSize) + 1);
		const nodeCount = this.columns * this.rows * LEVELS;
		this.heights = new Float32Array(nodeCount).fill(Number.NaN);
		this.edges = new Uint8Array(nodeCount * DIRECTIONS.length);
		this.failedNodes = new Set();
		this.wallPenalty = new Float32Array(nodeCount).fill(Number.NaN);
		this.cost = new Float32Array(nodeCount).fill(Number.POSITIVE_INFINITY);
		this.cameFrom = new Int32Array(nodeCount).fill(-1);
		this.closed = new Uint8Array(nodeCount);
		this.touched = [];
		this.heapNodes = [];
		this.heapScores = [];
		this.nextColumn = 0;
		this.ray = new Ray(new Vector3(), new Vector3(0, -1, 0));
		this.hit = new Vector3();
		this.normal = new Vector3();
		this.triangles = [];
		this.surfaces = [];
		this.probe = new Vector3();
		this.from = new Vector3();
		this.to = new Vector3();
	}

	get isComplete() {
		return this.nextColumn >= this.columns * this.rows;
	}

	/** Probes columns until the time budget runs out; returns true once every column is done. */
	build(budgetMs = Number.POSITIVE_INFINITY) {
		const deadline = performance.now() + budgetMs;
		const total = this.columns * this.rows;
		while (this.nextColumn < total) {
			this.probeColumn(this.nextColumn);
			this.nextColumn++;
			if ((this.nextColumn & 31) === 0 && performance.now() >= deadline) break;
		}
		return this.isComplete;
	}

	probeColumn(column) {
		const x = this.minX + (column % this.columns) * this.cellSize;
		const z = this.minZ + Math.floor(column / this.columns) * this.cellSize;
		this.ray.origin.set(x, this.maxY + 0.01, z);
		this.triangles.length = 0;
		this.collisionWorld.octree.getRayTriangles(this.ray, this.triangles);

		const surfaces = this.surfaces;
		surfaces.length = 0;
		for (const triangle of this.triangles) {
			triangle.getNormal(this.normal);
			if (Math.abs(this.normal.y) < MIN_WALKABLE_NORMAL_Y) continue;
			if (!this.ray.intersectTriangle(triangle.a, triangle.b, triangle.c, false, this.hit)) continue;
			if (this.hit.y < this.minY || this.hit.y > this.maxY) continue;
			surfaces.push(this.hit.y);
		}
		this.triangles.length = 0;
		surfaces.sort((a, b) => b - a);

		let level = 0;
		let previous = Number.POSITIVE_INFINITY;
		for (let index = 0; index < surfaces.length && index < MAX_SURFACES && level < LEVELS; index++) {
			const y = surfaces[index];
			if (previous - y < SURFACE_MERGE_DISTANCE) continue;
			previous = y;
			this.probe.set(x, y, z);
			if (this.collisionWorld.isBodyBlocked(this.probe)) continue;
			this.heights[column * LEVELS + level] = y;
			level++;
		}
	}

	nodePosition(node, target = { x: 0, y: 0, z: 0 }) {
		const column = Math.floor(node / LEVELS);
		target.x = this.minX + (column % this.columns) * this.cellSize;
		target.y = this.heights[node];
		target.z = this.minZ + Math.floor(column / this.columns) * this.cellSize;
		return target;
	}

	nearestNode(point) {
		const centerI = Math.round((point.x - this.minX) / this.cellSize);
		const centerJ = Math.round((point.z - this.minZ) / this.cellSize);
		let best = -1;
		let bestScore = Number.POSITIVE_INFINITY;
		for (let ring = 0; ring <= NEAREST_SEARCH_RINGS; ring++) {
			for (let j = centerJ - ring; j <= centerJ + ring; j++) {
				for (let i = centerI - ring; i <= centerI + ring; i++) {
					if (Math.max(Math.abs(i - centerI), Math.abs(j - centerJ)) !== ring) continue;
					if (i < 0 || j < 0 || i >= this.columns || j >= this.rows) continue;
					for (let level = 0; level < LEVELS; level++) {
						const node = (j * this.columns + i) * LEVELS + level;
						const height = this.heights[node];
						if (Number.isNaN(height)) break;
						const dy = Math.abs(height - point.y);
						if (dy > NEAREST_MAX_HEIGHT_DIFFERENCE) continue;
						const dx = this.minX + i * this.cellSize - point.x;
						const dz = this.minZ + j * this.cellSize - point.z;
						const score = Math.hypot(dx, dz) + dy * 0.5;
						if (score < bestScore) {
							bestScore = score;
							best = node;
						}
					}
				}
			}
			if (best !== -1) return best;
		}
		return -1;
	}

	nearestPoint(point) {
		const node = this.nearestNode(point);
		return node === -1 ? null : this.nodePosition(node);
	}

	/** Neighbour node in a direction, choosing the level reachable by a step or small drop. */
	neighbour(node, direction) {
		const column = Math.floor(node / LEVELS);
		const i = (column % this.columns) + DIRECTIONS[direction][0];
		const j = Math.floor(column / this.columns) + DIRECTIONS[direction][1];
		if (i < 0 || j < 0 || i >= this.columns || j >= this.rows) return -1;
		const height = this.heights[node];
		let best = -1;
		let bestDifference = Number.POSITIVE_INFINITY;
		for (let level = 0; level < LEVELS; level++) {
			const candidate = (j * this.columns + i) * LEVELS + level;
			const candidateHeight = this.heights[candidate];
			if (Number.isNaN(candidateHeight)) break;
			const rise = candidateHeight - height;
			if (rise > MAX_STEP_HEIGHT || -rise > MAX_DROP_HEIGHT) continue;
			if (Math.abs(rise) < bestDifference) {
				bestDifference = Math.abs(rise);
				best = candidate;
			}
		}
		return best;
	}

	edgeOpen(node, direction) {
		if (this.failedNodes.size > 0 && this.failedNodes.has(this.neighbour(node, direction))) return false;
		const slot = node * DIRECTIONS.length + direction;
		if (this.edges[slot] !== EDGE_UNKNOWN) return this.edges[slot] === EDGE_OPEN;
		const target = this.neighbour(node, direction);
		let open = target !== -1;
		if (open && direction >= 4) {
			const [alongX, alongZ] = DIAGONAL_PARTS[direction];
			open = this.edgeOpen(node, alongX) && this.edgeOpen(node, alongZ);
		}
		// A step is taken straight on, and only where the stair has no drop
		// beside it, so a route cannot clip the side of a stair next to the
		// open edge it overhangs.
		if (open && Math.abs(this.heights[target] - this.heights[node]) > LEVEL_TOLERANCE) {
			open = direction < 4 && !this.dropBeside(target, direction) && !this.dropBeside(node, direction);
		}
		if (open) open = !this.sweepBlocked(this.nodePosition(node), this.nodePosition(target));
		this.edges[slot] = open ? EDGE_OPEN : EDGE_BLOCKED;
		return open;
	}

	/**
	 * Whether either side of a node, across the direction of travel, falls
	 * away below it. It reads the ground itself, since the cell beside a step
	 * is often too close to the step for her body and records no surface.
	 */
	dropBeside(node, direction) {
		const position = this.nodePosition(node, this.from);
		for (const side of direction < 2 ? [2, 3] : [0, 1]) {
			const x = position.x + DIRECTIONS[side][0] * this.cellSize;
			const z = position.z + DIRECTIONS[side][1] * this.cellSize;
			const ground = getGroundHeight(x, z, this.collisionWorld.octree, position.y - DROP_SEARCH_DEPTH, position.y + MAX_STEP_HEIGHT);
			if (ground !== null && ground < position.y - LEVEL_TOLERANCE) return true;
		}
		return false;
	}

	/** Marks the move from a point toward another as impassable, after a resident failed to make it. */
	blockStep(from, toward) {
		const node = this.nearestNode(from);
		if (node === -1) return;
		const angle = Math.atan2(toward.z - from.z, toward.x - from.x);
		let direction = 0;
		let best = Number.POSITIVE_INFINITY;
		DIRECTIONS.forEach(([dx, dz], index) => {
			const difference = Math.abs(Math.atan2(Math.sin(Math.atan2(dz, dx) - angle), Math.cos(Math.atan2(dz, dx) - angle)));
			if (difference < best) {
				best = difference;
				direction = index;
			}
		});
		this.edges[node * DIRECTIONS.length + direction] = EDGE_BLOCKED;
		const target = this.neighbour(node, direction);
		if (target === -1) return;
		this.edges[target * DIRECTIONS.length + REVERSE_DIRECTION[direction]] = EDGE_BLOCKED;
		this.failedNodes.add(target);
	}

	/** Body sweep between two points, taken at the higher of the two so a step's riser does not count as a wall. */
	sweepBlocked(a, b, radius = CHARACTER_RADIUS) {
		const y = Math.max(a.y, b.y);
		this.from.set(a.x, y, a.z);
		this.to.set(b.x, y, b.z);
		return this.collisionWorld.isBodyBlocked(this.from, this.to, radius);
	}

	/** Extra cost of standing on a node close to a wall, so routes keep to the middle of doors and corridors. */
	penaltyAt(node) {
		if (Number.isNaN(this.wallPenalty[node])) {
			this.wallPenalty[node] = this.collisionWorld.isBodyBlocked(this.nodePosition(node, this.probe), this.probe, CHARACTER_RADIUS + WALL_MARGIN) ? WALL_PENALTY : 0;
		}
		return this.wallPenalty[node];
	}

	resetSearch() {
		for (const node of this.touched) {
			this.cost[node] = Number.POSITIVE_INFINITY;
			this.cameFrom[node] = -1;
			this.closed[node] = 0;
		}
		this.touched.length = 0;
		this.heapNodes.length = 0;
		this.heapScores.length = 0;
	}

	heapPush(node, score) {
		const nodes = this.heapNodes;
		const scores = this.heapScores;
		let index = nodes.length;
		nodes.push(node);
		scores.push(score);
		while (index > 0) {
			const parent = (index - 1) >> 1;
			if (scores[parent] <= score) break;
			nodes[index] = nodes[parent];
			scores[index] = scores[parent];
			index = parent;
		}
		nodes[index] = node;
		scores[index] = score;
	}

	heapPop() {
		const nodes = this.heapNodes;
		const scores = this.heapScores;
		const top = nodes[0];
		const lastNode = nodes.pop();
		const lastScore = scores.pop();
		if (nodes.length > 0) {
			let index = 0;
			while (true) {
				const left = index * 2 + 1;
				if (left >= nodes.length) break;
				const right = left + 1;
				const child = right < nodes.length && scores[right] < scores[left] ? right : left;
				if (scores[child] >= lastScore) break;
				nodes[index] = nodes[child];
				scores[index] = scores[child];
				index = child;
			}
			nodes[index] = lastNode;
			scores[index] = lastScore;
		}
		return top;
	}

	heuristic(node, goal) {
		const a = Math.floor(node / LEVELS);
		const b = Math.floor(goal / LEVELS);
		const dx = Math.abs((a % this.columns) - (b % this.columns));
		const dz = Math.abs(Math.floor(a / this.columns) - Math.floor(b / this.columns));
		return (Math.max(dx, dz) + (Math.SQRT2 - 1) * Math.min(dx, dz)) * this.cellSize;
	}

	search(start, goal) {
		this.resetSearch();
		this.cost[start] = 0;
		this.touched.push(start);
		this.heapPush(start, this.heuristic(start, goal));
		let expansions = 0;

		while (this.heapNodes.length > 0 && expansions < MAX_EXPANSIONS) {
			const node = this.heapPop();
			if (this.closed[node]) continue;
			if (node === goal) return true;
			this.closed[node] = 1;
			expansions++;

			for (let direction = 0; direction < DIRECTIONS.length; direction++) {
				if (!this.edgeOpen(node, direction)) continue;
				const next = this.neighbour(node, direction);
				if (this.closed[next]) continue;
				const step = (direction >= 4 ? Math.SQRT2 : 1) * this.cellSize + Math.abs(this.heights[next] - this.heights[node]) + this.penaltyAt(next);
				const candidate = this.cost[node] + step;
				if (candidate >= this.cost[next]) continue;
				if (this.cost[next] === Number.POSITIVE_INFINITY) this.touched.push(next);
				this.cost[next] = candidate;
				this.cameFrom[next] = node;
				this.heapPush(next, candidate + this.heuristic(next, goal));
			}
		}
		return false;
	}

	/**
	 * A* toward a point she may not be able to stand on, such as a seat: the
	 * goal is a virtual node reached from any ground within `radius` of the
	 * point and at most a step above it, costing the remaining horizontal
	 * distance. Returns the ground node she should stop on, or -1.
	 */
	searchNear(start, point, radius) {
		this.resetSearch();
		const minY = point.y - NEAREST_MAX_HEIGHT_DIFFERENCE;
		const maxY = point.y + MAX_STEP_HEIGHT;
		const position = { x: 0, y: 0, z: 0 };
		const distance = (node) => {
			this.nodePosition(node, position);
			return Math.hypot(position.x - point.x, position.z - point.z);
		};
		this.cost[start] = 0;
		this.touched.push(start);
		this.heapPush(start, distance(start));
		let best = -1;
		let bestScore = Number.POSITIVE_INFINITY;
		let expansions = 0;

		while (this.heapNodes.length > 0 && expansions < MAX_EXPANSIONS && this.heapScores[0] < bestScore) {
			const node = this.heapPop();
			if (this.closed[node]) continue;
			this.closed[node] = 1;
			expansions++;

			const remaining = distance(node);
			const height = this.heights[node];
			if (remaining <= radius && height >= minY && height <= maxY && this.cost[node] + remaining < bestScore) {
				best = node;
				bestScore = this.cost[node] + remaining;
			}

			for (let direction = 0; direction < DIRECTIONS.length; direction++) {
				if (!this.edgeOpen(node, direction)) continue;
				const next = this.neighbour(node, direction);
				if (this.closed[next]) continue;
				const step = (direction >= 4 ? Math.SQRT2 : 1) * this.cellSize + Math.abs(this.heights[next] - this.heights[node]) + this.penaltyAt(next);
				const candidate = this.cost[node] + step;
				if (candidate >= this.cost[next]) continue;
				if (this.cost[next] === Number.POSITIVE_INFINITY) this.touched.push(next);
				this.cost[next] = candidate;
				this.cameFrom[next] = node;
				this.heapPush(next, candidate + distance(next));
			}
		}
		return best;
	}

	/** Whether a straight walk between two points stays on reachable ground without hitting anything. */
	clearLine(a, b) {
		const length = Math.hypot(b.x - a.x, b.z - a.z);
		const samples = Math.max(1, Math.ceil(length / SIGHT_SAMPLE_SPACING));
		let previous = a;
		for (let index = 1; index <= samples; index++) {
			const t = index / samples;
			const x = a.x + (b.x - a.x) * t;
			const z = a.z + (b.z - a.z) * t;
			const column = this.columnNear(x, z);
			if (column === -1) return false;
			let height = Number.NaN;
			for (let level = 0; level < LEVELS; level++) {
				const candidate = this.heights[column * LEVELS + level];
				if (Number.isNaN(candidate)) break;
				const rise = candidate - previous.y;
				if (rise <= MAX_STEP_HEIGHT && -rise <= MAX_DROP_HEIGHT) {
					if (this.failedNodes.has(column * LEVELS + level)) return false;
					height = candidate;
					break;
				}
			}
			if (Number.isNaN(height) || Math.abs(height - previous.y) > LEVEL_TOLERANCE) return false;
			const point = { x, y: height, z };
			if (this.sweepBlocked(previous, point, CHARACTER_RADIUS + SMOOTHING_MARGIN)) return false;
			previous = point;
		}
		return true;
	}

	columnNear(x, z) {
		const i = Math.round((x - this.minX) / this.cellSize);
		const j = Math.round((z - this.minZ) / this.cellSize);
		if (i < 0 || j < 0 || i >= this.columns || j >= this.rows) return -1;
		return j * this.columns + i;
	}

	/** Route between two points as waypoints, or null when there is none. */
	findPath(from, to) {
		if (!this.isComplete) return null;
		const start = this.nearestNode(from);
		const goal = this.nearestNode(to);
		if (start === -1 || goal === -1) return null;
		if (start !== goal && !this.search(start, goal)) return null;
		return this.pathBetween(start, goal);
	}

	/** Route to the reachable ground closest to a point she cannot stand on, such as the floor beside a seat, or null when there is none. */
	findPathNear(from, to, radius = APPROACH_RADIUS) {
		if (!this.isComplete) return null;
		const start = this.nearestNode(from);
		if (start === -1) return null;
		const goal = this.searchNear(start, to, radius);
		return goal === -1 ? null : this.pathBetween(start, goal);
	}

	pathBetween(start, goal) {
		const nodes = [goal];
		while (nodes[nodes.length - 1] !== start) nodes.push(this.cameFrom[nodes[nodes.length - 1]]);
		const points = nodes.reverse().map((node) => this.nodePosition(node));
		return this.smooth(points);
	}

	smooth(points) {
		if (points.length <= 2) return points;
		const smoothed = [points[0]];
		let anchor = 0;
		while (anchor < points.length - 1) {
			let furthest = anchor + 1;
			const limit = Math.min(points.length - 1, anchor + SMOOTHING_LOOKAHEAD);
			while (furthest < limit && this.clearLine(points[anchor], points[furthest + 1])) furthest++;
			smoothed.push(points[furthest]);
			anchor = furthest;
		}
		return smoothed;
	}
}

export function createNavigationGrid(collisionWorld, options) {
	return new NavigationGrid(collisionWorld, options);
}

export function buildNavigationGrid(collisionWorld, options) {
	const grid = new NavigationGrid(collisionWorld, options);
	grid.build();
	return grid;
}
