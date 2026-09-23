import { Box3, OrthographicCamera, Plane, SRGBColorSpace, Vector3, WebGLRenderTarget } from 'three';
import { PLAN_CUT_HEIGHT, floorBounds, floorGroundHeight } from './worldMapProjection.js';

const MAP_RESOLUTION = 1024;
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

	const camera = new OrthographicCamera(-width / 2, width / 2, depth / 2, -depth / 2, 0.1, 20);
	camera.position.set((bounds.minX + bounds.maxX) / 2, cutHeight + 1, (bounds.minZ + bounds.maxZ) / 2);
	camera.up.set(0, 0, -1);
	camera.lookAt(camera.position.x, cutHeight - 10, camera.position.z);
	camera.updateMatrixWorld();

	const target = new WebGLRenderTarget(pixelWidth, pixelHeight);
	target.texture.colorSpace = SRGBColorSpace;
	const previousTarget = renderer.getRenderTarget();
	const previousPlanes = renderer.clippingPlanes;
	renderer.clippingPlanes = [new Plane(new Vector3(0, -1, 0), cutHeight)];
	renderer.setRenderTarget(target);
	renderer.render(scene, camera);
	const pixels = new Uint8Array(pixelWidth * pixelHeight * 4);
	renderer.readRenderTargetPixels(target, 0, 0, pixelWidth, pixelHeight, pixels);
	renderer.setRenderTarget(previousTarget);
	renderer.clippingPlanes = previousPlanes;
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
	return canvas.toDataURL('image/png');
}

/**
 * One top-down image per floor, cut just above head height so walls read
 * like a floor plan and the floors above are removed.
 */
export function renderFloorMaps({ renderer, scene, layout, environmentRoot, fallbackGroundY }) {
	const floors = layout?.floors?.length ? layout.floors : [{ id: null, name: null }];
	const fallback = environmentRoot ? environmentBounds(environmentRoot) : null;

	return floors.flatMap((floor) => {
		const bounds = floorBounds(layout, floor.id) ?? fallback;
		if (!bounds) return [];
		const cutHeight = floorGroundHeight(layout, floor.id, fallbackGroundY) + PLAN_CUT_HEIGHT;
		return [{ floorId: floor.id, name: floor.name, bounds, url: renderPlan(renderer, scene, bounds, cutHeight) }];
	});
}
