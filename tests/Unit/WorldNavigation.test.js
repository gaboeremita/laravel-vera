import assert from 'node:assert/strict';
import { test } from 'node:test';
import { BoxGeometry, Group, Mesh, MeshBasicMaterial, PlaneGeometry } from 'three';
import { WorldCollision } from '../../resources/js/components/world/collisionCheck.js';
import { buildNavigationGrid } from '../../resources/js/components/world/worldNavigation.js';

const BOUNDS = { minX: -8, maxX: 8, minZ: -8, maxZ: 8 };

function box(width, height, depth, x, y, z) {
	const mesh = new Mesh(new BoxGeometry(width, height, depth), new MeshBasicMaterial());
	mesh.position.set(x, y, z);
	return mesh;
}

function navigate(context, objects, { withFloor = true, minY = -2, maxY = 3 } = {}) {
	const scene = new Group();
	if (withFloor) scene.add(box(20, 0.1, 20, 0, -0.05, 0));
	scene.add(...objects);
	scene.updateMatrixWorld(true);
	const world = new WorldCollision(scene);
	context.after(() => world.dispose());
	return buildNavigationGrid(world, { bounds: BOUNDS, minY, maxY });
}

test('a route goes through a doorway instead of the wall', (context) => {
	const grid = navigate(context, [
		box(7, 3, 0.2, -4, 1.5, 0),
		box(7, 3, 0.2, 4, 1.5, 0),
	]);
	const path = grid.findPath({ x: 3, y: 0, z: 4 }, { x: 3, y: 0, z: -4 });
	assert.ok(path);
	const crossing = path.find((point, index) => index > 0 && Math.sign(point.z) !== Math.sign(path[index - 1].z));
	const previous = path[path.indexOf(crossing) - 1];
	const t = previous.z / (previous.z - crossing.z);
	const crossingX = previous.x + (crossing.x - previous.x) * t;
	assert.ok(Math.abs(crossingX) < 0.5, `crossed the wall at x=${crossingX}`);
});

test('a staircase of 0.2 m steps is climbed and descended', (context) => {
	const steps = Array.from({ length: 5 }, (_, index) => box(3, 0.2 * (index + 1), 0.4, 0, 0.1 * (index + 1), 2 - index * 0.4));
	const landing = box(3, 1, 3.2, 0, 0.5, -1.4);
	const grid = navigate(context, [...steps, landing]);
	const up = grid.findPath({ x: 0, y: 0, z: 5 }, { x: 0, y: 1, z: -2 });
	assert.ok(up);
	assert.ok(Math.abs(up.at(-1).y - 1) < 0.05);
	const down = grid.findPath({ x: 0, y: 1, z: -2 }, { x: 0, y: 0, z: 5 });
	assert.ok(down);
	assert.ok(Math.abs(down.at(-1).y) < 0.05);
});

test('a 1.4 m drop is never taken', (context) => {
	const platform = box(4, 1.4, 4, 0, 0.7, 0);
	const grid = navigate(context, [platform]);
	assert.equal(grid.findPath({ x: 0, y: 1.4, z: 0 }, { x: 0, y: 0, z: 6 }), null);
});

test('a passable water surface is not walkable but the pool floor beneath it is', (context) => {
	const deckNorth = box(20, 1.4, 6, 0, -0.7, 5);
	const deckSouth = box(20, 1.4, 6, 0, -0.7, -5);
	const poolFloor = box(20, 0.1, 20, 0, -1.45, 0);
	const surface = new Mesh(new PlaneGeometry(20, 4).rotateX(-Math.PI / 2), new MeshBasicMaterial());
	surface.position.set(0, -0.05, 0);
	surface.userData.passable = true;
	const grid = navigate(context, [deckNorth, deckSouth, poolFloor, surface], { withFloor: false });
	const node = grid.nearestPoint({ x: 0, y: -1.4, z: 0 });
	assert.ok(node);
	assert.ok(Math.abs(node.y + 1.4) < 0.05);
	assert.equal(grid.findPath({ x: 0, y: 0, z: 4 }, { x: 0, y: -1.4, z: 0 }), null);
});

test('an enclosed target is unreachable', (context) => {
	const walls = [
		box(3, 3, 0.2, 5, 1.5, 3.5),
		box(3, 3, 0.2, 5, 1.5, 6.5),
		box(0.2, 3, 3, 3.5, 1.5, 5),
		box(0.2, 3, 3, 6.5, 1.5, 5),
	];
	const grid = navigate(context, walls);
	assert.equal(grid.findPath({ x: -5, y: 0, z: -5 }, { x: 5, y: 0, z: 5 }), null);
});

test('an open route is smoothed to a straight line', (context) => {
	const grid = navigate(context, []);
	const path = grid.findPath({ x: -5, y: 0, z: -5 }, { x: 5, y: 0, z: 4 });
	assert.ok(path);
	assert.equal(path.length, 2);
});

test('a seat is approached from the floor beside it', (context) => {
	const bed = box(2, 0.6, 2.2, 0, 0.3, 0);
	const grid = navigate(context, [bed]);
	const seat = { x: 0, y: 0.7, z: 1.4 };
	assert.equal(grid.findPath({ x: 0, y: 0, z: 6 }, seat), null);
	const path = grid.findPathNear({ x: 0, y: 0, z: 6 }, seat);
	assert.ok(path);
	const end = path.at(-1);
	assert.ok(Math.abs(end.y) < 0.05, `ended at height ${end.y}`);
	assert.ok(Math.hypot(end.x - seat.x, end.z - seat.z) <= 1.2);
});

test('a seat with no floor nearby has no approach', (context) => {
	const walls = [
		box(3, 3, 0.2, 5, 1.5, 3.5),
		box(3, 3, 0.2, 5, 1.5, 6.5),
		box(0.2, 3, 3, 3.5, 1.5, 5),
		box(0.2, 3, 3, 6.5, 1.5, 5),
	];
	const grid = navigate(context, walls);
	assert.equal(grid.findPathNear({ x: -5, y: 0, z: -5 }, { x: 5, y: 0.5, z: 5 }, 0.8), null);
});

test('steps into a pit are taken through their middle, away from the drop beside them', (context) => {
	const pitFloor = box(20, 0.1, 20, 0, -0.65, 0);
	const deck = box(20, 0.6, 8.2, 0, -0.3, 3.9);
	const upperStep = box(2, 0.4, 0.4, 0, -0.4, -0.4);
	const lowerStep = box(2, 0.2, 0.4, 0, -0.5, -0.8);
	const grid = navigate(context, [pitFloor, deck, upperStep, lowerStep], { withFloor: false });
	const path = grid.findPath({ x: 4, y: 0, z: 3 }, { x: 4, y: -0.6, z: -4 });
	assert.ok(path);
	const onSteps = path.filter((point) => point.z < -0.2 && point.z > -1.0);
	assert.ok(onSteps.length > 0);
	for (const point of onSteps) assert.ok(Math.abs(point.x) <= 0.5, `stepped down at x=${point.x}`);
});

test('a step a resident failed to make is avoided on the next plan', (context) => {
	const grid = navigate(context, []);
	const direct = grid.findPath({ x: 0, y: 0, z: 2 }, { x: 0, y: 0, z: -2 });
	assert.equal(direct.length, 2);
	grid.blockStep({ x: 0, y: 0, z: 0 }, { x: 0, y: 0, z: -1 });
	const detour = grid.findPath({ x: 0, y: 0, z: 2 }, { x: 0, y: 0, z: -2 });
	assert.ok(detour);
	assert.ok(detour.length > 2);
});

test('a route through a wide doorway keeps to its middle', (context) => {
	const grid = navigate(context, [
		box(6.8, 3, 0.2, -4.6, 1.5, 0),
		box(6.8, 3, 0.2, 4.6, 1.5, 0),
	]);
	const path = grid.findPath({ x: -3, y: 0, z: 4 }, { x: -3, y: 0, z: -4 });
	assert.ok(path);
	const crossing = path.find((point, index) => index > 0 && Math.sign(point.z) !== Math.sign(path[index - 1].z));
	const previous = path[path.indexOf(crossing) - 1];
	const t = previous.z / (previous.z - crossing.z);
	const crossingX = previous.x + (crossing.x - previous.x) * t;
	assert.ok(crossingX >= -0.8, `crossed the doorway at x=${crossingX}, next to its frame at x=-1.2`);
});
