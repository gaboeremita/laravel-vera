import assert from 'node:assert/strict';
import { test } from 'node:test';
import { BoxGeometry, BufferGeometry, Float32BufferAttribute, Group, InstancedMesh, Matrix4, Mesh, MeshBasicMaterial, PlaneGeometry, Vector3 } from 'three';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';
import { WorldCollision } from '../../resources/js/components/world/collisionCheck.js';

function createWorld(context, ...objects) {
	const scene = new Group();
	const floor = new Mesh(new BoxGeometry(20, 0.1, 20), new MeshBasicMaterial());
	floor.position.y = -0.05;
	scene.add(floor, ...objects);
	const world = new WorldCollision(scene);
	context.after(() => {
		world.dispose();
		scene.traverse((node) => {
			if (!node.isMesh) return;
			node.geometry.dispose();
			node.material.dispose();
		});
	});
	return world;
}

function wall() {
	const mesh = new Mesh(new PlaneGeometry(10, 3), new MeshBasicMaterial());
	mesh.name = 'WallLargeB';
	mesh.position.y = 1.5;
	return mesh;
}

for (const startZ of [-2, 2]) {
	test(`an ordinary one-sided wall blocks movement from z=${startZ}`, (context) => {
		const world = createWorld(context, wall());
		const position = new Vector3(0, 0, startZ);
		world.move(position, 0, -2 * startZ);
		assert.ok(Math.sign(position.z) === Math.sign(startZ));
		assert.ok(Math.abs(position.z) >= 0.25);
		assert.ok(Math.abs(position.z) < 0.4);
		assert.ok(Math.abs(position.y) < 0.001);
	});
}

test('named invisible proxies supplement ordinary visible geometry', (context) => {
	const proxy = new Group();
	proxy.name = 'Collision';
	proxy.visible = false;
	const proxyWall = wall();
	proxyWall.position.z = 3;
	proxy.add(proxyWall);
	const world = createWorld(context, wall(), proxy);
	const towardWall = new Vector3(0, 0, 1);
	const towardProxy = new Vector3(0, 0, 2);
	world.move(towardWall, 0, -2);
	world.move(towardProxy, 0, 2);
	assert.ok(towardWall.z >= 0.25);
	assert.ok(towardProxy.z <= 2.75);
	assert.equal(proxyWall.visible, false);
});

test('a doorway in one mesh stays open while its jamb blocks the body width', (context) => {
	const parts = [
		new BoxGeometry(2.4, 3, 0.2).translate(-1.8, 1.5, 0),
		new BoxGeometry(2.4, 3, 0.2).translate(1.8, 1.5, 0),
		new BoxGeometry(1.2, 0.8, 0.2).translate(0, 2.6, 0),
	];
	const doorway = new Mesh(mergeGeometries(parts), new MeshBasicMaterial());
	parts.forEach((geometry) => geometry.dispose());
	const world = createWorld(context, doorway);
	const centered = new Vector3(0, 0, 2);
	const clippingJamb = new Vector3(0.5, 0, 2);
	world.move(centered, 0, -4);
	world.move(clippingJamb, 0, -4);
	assert.ok(Math.abs(centered.z + 2) < 0.001);
	assert.ok(clippingJamb.z >= 0.35);
});

test('diagonal input slides along a wall without crossing it', (context) => {
	const world = createWorld(context, wall());
	const position = new Vector3(0, 0, 0.3);
	world.move(position, 2, -2);
	assert.ok(position.x > 1.9);
	assert.ok(position.z >= 0.25);
});

test('tabletops cannot be selected as a new floor through furniture', (context) => {
	const tabletop = new Mesh(new BoxGeometry(2, 0.15, 1), new MeshBasicMaterial());
	tabletop.position.y = 0.9;
	const world = createWorld(context, tabletop);
	const position = new Vector3(0, 0, 2);
	world.move(position, 0, -4);
	assert.ok(position.z >= 0.75);
	assert.ok(Math.abs(position.y) < 0.001);
});

test('short thresholds can be crossed and stepped down from', (context) => {
	const step = new Mesh(new BoxGeometry(2, 0.2, 1), new MeshBasicMaterial());
	step.position.y = 0.1;
	const world = createWorld(context, step);
	const position = new Vector3(0, 0, 2);
	world.move(position, 0, -2);
	assert.ok(Math.abs(position.y - 0.2) < 0.001);
	world.move(position, 0, -2);
	assert.ok(Math.abs(position.z + 2) < 0.001);
	assert.ok(Math.abs(position.y) < 0.001);
});

test('the player follows a ramp in both directions', (context) => {
	const geometry = new BufferGeometry();
	geometry.setAttribute('position', new Float32BufferAttribute([
		-1, 0, -2, 1, 1, -2, 1, 1, 2,
		-1, 0, -2, 1, 1, 2, -1, 0, 2,
	], 3));
	const world = createWorld(context, new Mesh(geometry, new MeshBasicMaterial()));
	const position = new Vector3(-1.5, 0, 0);
	world.move(position, 2.3, 0);
	assert.ok(Math.abs(position.x - 0.8) < 0.001);
	assert.ok(Math.abs(position.y - 0.9) < 0.001);
	world.move(position, -2.3, 0);
	assert.ok(Math.abs(position.x + 1.5) < 0.001);
	assert.ok(Math.abs(position.y) < 0.001);
});

test('world and instance transforms are included in collisions', (context) => {
	const instances = new InstancedMesh(new PlaneGeometry(2, 3), new MeshBasicMaterial(), 2);
	instances.setMatrixAt(0, new Matrix4().makeTranslation(-3, 1.5, 0));
	instances.setMatrixAt(1, new Matrix4().makeTranslation(3, 1.5, 0));
	const parent = new Group();
	parent.position.z = -1;
	parent.scale.x = -1;
	parent.add(instances);
	const world = createWorld(context, parent);
	for (const x of [-3, 3]) {
		const position = new Vector3(x, 0, 1);
		world.move(position, 0, -4);
		assert.ok(position.z >= -0.75);
		assert.ok(position.z < -0.6);
	}
});

test('a blocked spawn is moved to a nearby clear floor', (context) => {
	const world = createWorld(context, wall());
	const position = world.findSpawn(new Vector3(0, 0, 0));
	assert.ok(position);
	assert.ok(Math.abs(position.z) > 0.25);
	assert.equal(world.isBodyBlocked(position), false);
	assert.ok(Math.abs(position.y) < 0.001);
});

test('a saved eye-level position is restored to its original floor position', (context) => {
	const world = createWorld(context);
	const fallback = new Vector3(0, 0, 0);
	const position = world.restorePlayerPosition({ x: 2, y: 1.6, z: 2 }, fallback);
	assert.ok(Math.abs(position.x - 2) < 0.001);
	assert.ok(Math.abs(position.y) < 0.001);
	assert.ok(Math.abs(position.z - 2) < 0.001);
});

test('a saved position inside a wall is restored to nearby safe ground', (context) => {
	const world = createWorld(context, wall());
	const fallback = new Vector3(4, 0, 4);
	const position = world.restorePlayerPosition({ x: 0, y: 1.6, z: 0 }, fallback);
	assert.ok(Math.abs(position.z) > 0.25);
	assert.equal(world.isBodyBlocked(position), false);
	assert.ok(Math.abs(position.y) < 0.001);
});

test('an invalid saved position falls back to the environment spawn', (context) => {
	const world = createWorld(context);
	const fallback = new Vector3(3, 0, 3);
	for (const savedPosition of [
		{ x: '3', y: 1.6, z: 3 },
		{ x: 3, y: null, z: 3 },
		{ x: 3, y: 1.6 },
	]) {
		const position = world.restorePlayerPosition(savedPosition, fallback);
		assert.deepEqual(position.toArray(), fallback.toArray());
		assert.notEqual(position, fallback);
	}
});

test('a room does not need a ceiling to provide a valid floor spawn', (context) => {
	const world = createWorld(context);
	const position = world.findSpawn(new Vector3(0, 0, 0));
	assert.ok(position);
	assert.ok(Math.abs(position.y) < 0.001);
});

test('an empty environment fails explicitly', () => {
	assert.throws(() => new WorldCollision(new Group()), /no geometry for collision/);
});
