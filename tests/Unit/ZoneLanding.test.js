import assert from 'node:assert/strict';
import { test } from 'node:test';
import { zoneLanding } from '../../resources/js/components/world/zoneLanding.js';

function stubGrid(points, islands = []) {
	return {
		isComplete: true,
		heights: points.map((point) => point.y),
		nodePosition(node, target = { x: 0, y: 0, z: 0 }) {
			Object.assign(target, points[node]);
			return target;
		},
		isIsland: (node) => islands.includes(node),
	};
}

const square = { id: 'square', parentId: null, outline: [[0, 0], [10, 0], [10, 10], [0, 10]], minY: -1, maxY: 3 };
const lShape = { id: 'l-shape', parentId: null, outline: [[0, 0], [10, 0], [10, 2], [2, 2], [2, 10], [0, 10]], minY: -1, maxY: 3 };
const upper = { id: 'upper', parentId: null, outline: [[0, 0], [10, 0], [10, 10], [0, 10]], minY: 4, maxY: 8 };

test('a convex zone lands on the walkable point nearest its middle', () => {
	const grid = stubGrid([{ x: 1, y: 0, z: 1 }, { x: 5.5, y: 0, z: 4.5 }, { x: 9, y: 0, z: 9 }]);
	assert.deepEqual(zoneLanding({ zones: [square] }, 'square', grid), { x: 5.5, y: 0, z: 4.5 });
});

test('a zone whose middle lies outside it lands inside it', () => {
	const grid = stubGrid([{ x: 4, y: 0, z: 4 }, { x: 1, y: 0, z: 5 }, { x: 15, y: 0, z: 15 }]);
	assert.deepEqual(zoneLanding({ zones: [lShape] }, 'l-shape', grid), { x: 1, y: 0, z: 5 });
});

test('a zone on an upper floor ignores ground outside its height range', () => {
	const grid = stubGrid([{ x: 5, y: 0, z: 5 }, { x: 2, y: 5, z: 2 }]);
	assert.deepEqual(zoneLanding({ zones: [upper] }, 'upper', grid), { x: 2, y: 5, z: 2 });
});

test('ground that leads nowhere is passed over for ground that does', () => {
	const grid = stubGrid([{ x: 5, y: 0, z: 5 }, { x: 3, y: 0, z: 3 }], [0]);
	assert.deepEqual(zoneLanding({ zones: [square] }, 'square', grid), { x: 3, y: 0, z: 3 });
});

test('a zone with no walkable point has nowhere to land', () => {
	const grid = stubGrid([{ x: 20, y: 0, z: 20 }]);
	assert.equal(zoneLanding({ zones: [square] }, 'square', grid), null);
});
