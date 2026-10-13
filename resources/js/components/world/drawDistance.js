import { Box3 } from 'three';

/** Full detail is drawn only this close to the user; past it a chunk shows its low-detail version, or nothing. */
export const DRAW_DISTANCE = 60;
/** How far the camera sees in a world file without low-detail versions. */
export const DEFAULT_CAMERA_FAR = 100;
/** Over this band before a chunk's draw distance its full and low-detail versions dissolve into each other. */
export const FADE_BAND = 6;
export const BACKDROP_LAYER = 1;

const DITHER_NOISE = 'fract(52.9829189 * fract(dot(gl_FragCoord.xy, vec2(0.06711056, 0.00583715))))';

/** How much of a chunk's full version shows at this distance: all of it inside the fade band, none past its draw distance. */
export function fullDetailShare(distance, drawDistance = DRAW_DISTANCE) {
	const reach = Math.min(drawDistance, DRAW_DISTANCE);
	return Math.min(1, Math.max(0, (reach - distance) / FADE_BAND));
}

export function isWithinDrawDistance(distance) {
	return distance <= DRAW_DISTANCE;
}

/**
 * Whether a node is drawn as part of the world, rather than as backdrop or
 * as a low-detail version, which collide with nothing and stay off the map.
 */
export function isWorldGeometry(node) {
	for (let current = node; current; current = current.parent) {
		if (current.userData?.backdrop === true || current.userData?.lowDetail === true) return false;
	}
	return true;
}

/**
 * The full and low-detail versions dissolve into each other through
 * complementary screen-door patterns, so every pixel shows exactly one of
 * them. Each chunk gets its own copies of its materials, since three.js only
 * uploads a material's uniforms when the material changes between draws.
 */
function ditheredMaterial(material, share, lowDetail) {
	const dithered = material.clone();
	const kept = lowDetail ? `${DITHER_NOISE} >= detailShare` : `${DITHER_NOISE} < detailShare`;
	dithered.onBeforeCompile = (shader) => {
		shader.uniforms.detailShare = share;
		shader.fragmentShader = shader.fragmentShader
			.replace('void main() {', 'uniform float detailShare;\nvoid main() {')
			.replace('#include <clipping_planes_fragment>', `#include <clipping_planes_fragment>\n\tif (!(${kept})) discard;`);
	};
	dithered.customProgramCacheKey = () => (lowDetail ? 'low-detail-dither' : 'full-detail-dither');
	return dithered;
}

function ditherMeshes(meshes, share, lowDetail) {
	for (const mesh of meshes) {
		mesh.material = Array.isArray(mesh.material)
			? mesh.material.map((material) => ditheredMaterial(material, share, lowDetail))
			: ditheredMaterial(mesh.material, share, lowDetail);
	}
}

function expandByMesh(box, mesh) {
	if (!mesh.geometry.boundingBox) mesh.geometry.computeBoundingBox();
	box.union(mesh.geometry.boundingBox.clone().applyMatrix4(mesh.matrixWorld));
}

function collectChunk(node) {
	const chunk = { node, drawDistance: node.userData.drawDistance, full: [], low: [], box: new Box3(), share: { value: 1 } };
	const visit = (current, lowDetail) => {
		if (current !== node && typeof current.userData?.drawDistance === 'number') return;
		const low = lowDetail || current.userData?.lowDetail === true;
		if (current.isMesh) {
			if (low) chunk.low.push(current);
			else {
				chunk.full.push(current);
				expandByMesh(chunk.box, current);
			}
		}
		for (const child of current.children) visit(child, low);
	};
	visit(node, false);
	return chunk;
}

/**
 * Reads what a world file marks for distance: groups with a `drawDistance`
 * are chunks, drawn in full only that close (never past DRAW_DISTANCE) and
 * holding an optional `lowDetail` version shown beyond it; nodes marked
 * `backdrop` are drawn behind the world at any distance. A file without
 * these marks is drawn as it always was.
 */
export function prepareDrawDistance(root) {
	root.updateMatrixWorld(true);
	const chunks = [];
	const backdrop = [];
	root.traverse((node) => {
		if (typeof node.userData?.drawDistance === 'number') chunks.push(collectChunk(node));
		if (node.userData?.backdrop === true) backdrop.push(node);
	});
	const backdropBox = new Box3();
	for (const node of backdrop) {
		node.traverse((child) => {
			child.layers.set(BACKDROP_LAYER);
			if (child.isMesh) expandByMesh(backdropBox, child);
		});
	}
	for (const chunk of chunks) {
		ditherMeshes(chunk.full, chunk.share, false);
		ditherMeshes(chunk.low, chunk.share, true);
	}

	const worldBox = new Box3();
	root.traverse((node) => {
		if (node.isMesh && isWorldGeometry(node)) expandByMesh(worldBox, node);
	});
	const hasLowDetail = chunks.some((chunk) => chunk.low.length > 0);
	const worldReach = worldBox.isEmpty() ? 0 : worldBox.min.distanceTo(worldBox.max);
	const reachBox = backdropBox.clone().union(worldBox);

	return {
		chunks,
		cameraFar: hasLowDetail ? Math.max(DEFAULT_CAMERA_FAR, worldReach) : DEFAULT_CAMERA_FAR,
		backdropFar: backdropBox.isEmpty() ? null : reachBox.min.distanceTo(reachBox.max),
	};
}

function setVisible(meshes, visible) {
	for (const mesh of meshes) mesh.visible = visible;
}

/** Shows each chunk's full or low-detail version, or both mid-fade, for a camera at this point. */
export function updateDrawDistance(detail, point) {
	for (const chunk of detail.chunks) {
		const share = chunk.box.isEmpty() ? 1 : fullDetailShare(chunk.box.distanceToPoint(point), chunk.drawDistance);
		chunk.share.value = share;
		setVisible(chunk.full, share > 0);
		setVisible(chunk.low, share < 1);
	}
}

/** Every chunk in full and no low-detail version, as the floor maps are drawn. */
export function showFullDetail(detail) {
	for (const chunk of detail.chunks) {
		chunk.share.value = 1;
		setVisible(chunk.full, true);
		setVisible(chunk.low, false);
	}
}
