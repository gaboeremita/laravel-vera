import { Box3, OrthographicCamera, SRGBColorSpace, Vector3, WebGLRenderTarget } from 'three';
import { PLAN_CUT_HEIGHT, floorBounds, floorGroundHeight } from './worldMapProjection.js';

const MAP_RESOLUTION = 640;
const MAX_ENVIRONMENT_SPAN = 150;

/**
 * Bounds of the environment's own geometry for worlds without zones. Very
 * large meshes (sky domes, panoramas) are skipped so the map fits the level.
 */
export function environmentBounds(root) {
	const bounds = new Box3();
	const meshBounds = new Box3();
	const size = new Vector3();
	root.traverse((node) => {
		if (!node.isMesh) return;
		meshBounds.setFromObject(node);
		meshBounds.getSize(size);
		if (Math.max(size.x, size.z) > MAX_ENVIRONMENT_SPAN) return;
		bounds.union(meshBounds);
	});
	if (bounds.isEmpty()) return null;
	return { minX: bounds.min.x, maxX: bounds.max.x, minZ: bounds.min.z, maxZ: bounds.max.z };
}

function renderPlan(renderer, scene, bounds, cutHeight) {
	const width = bounds.maxX - bounds.minX;
	const depth = bounds.maxZ - bounds.minZ;
	const scale = MAP_RESOLUTION / Math.max(width, depth);
	const pixelWidth = Math.max(1, Math.round(width * scale));
	const pixelHeight = Math.max(1, Math.round(depth * scale));

	// The near plane at the cut height removes everything above it, without
	// renderer clipping planes, which would recompile every material's shader.
	const camera = new OrthographicCamera(-width / 2, width / 2, depth / 2, -depth / 2, 0.001, 20);
	camera.position.set((bounds.minX + bounds.maxX) / 2, cutHeight, (bounds.minZ + bounds.maxZ) / 2);
	camera.up.set(0, 0, -1);
	camera.lookAt(camera.position.x, cutHeight - 10, camera.position.z);
	camera.updateMatrixWorld();

	const target = new WebGLRenderTarget(pixelWidth, pixelHeight);
	target.texture.colorSpace = SRGBColorSpace;
	const previousTarget = renderer.getRenderTarget();
	renderer.setRenderTarget(target);
	renderer.render(scene, camera);
	const pixels = new Uint8Array(pixelWidth * pixelHeight * 4);
	renderer.readRenderTargetPixels(target, 0, 0, pixelWidth, pixelHeight, pixels);
	renderer.setRenderTarget(previousTarget);
	target.dispose();

	const canvas = document.createElement('canvas');
	canvas.width = pixelWidth;
	canvas.height = pixelHeight;
	const context = canvas.getContext('2d');
	const image = context.createImageData(pixelWidth, pixelHeight);
	for (let row = 0; row < pixelHeight; row++) {
		const source = (pixelHeight - 1 - row) * pixelWidth * 4;
		image.data.set(pixels.subarray(source, source + pixelWidth * 4), row * pixelWidth * 4);
	}
	context.putImageData(image, 0, 0);
	return new Promise((resolve) => canvas.toBlob((blob) => resolve(blob ?? null), 'image/jpeg', 0.85));
}

/**
 * One top-down image per floor, cut just above head height so walls read
 * like a floor plan and the floors above are removed.
 */
export async function renderFloorMaps({ renderer, scene, layout, environmentRoot, fallbackGroundY }) {
	const floors = layout?.floors?.length ? layout.floors : [{ id: null, name: null }];
	const fallback = floors.some((floor) => !floorBounds(layout, floor.id)) && environmentRoot ? environmentBounds(environmentRoot) : null;
	const maps = [];

	for (const floor of floors) {
		const bounds = floorBounds(layout, floor.id) ?? fallback;
		if (!bounds) continue;
		const cutHeight = floorGroundHeight(layout, floor.id, fallbackGroundY) + PLAN_CUT_HEIGHT;
		const blob = await renderPlan(renderer, scene, bounds, cutHeight);
		if (blob) maps.push({ floorId: floor.id, name: floor.name, bounds, blob });
	}

	return maps;
}
