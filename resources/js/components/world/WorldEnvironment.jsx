import { useEffect, useRef } from 'react';
import { useThree } from '@react-three/fiber';
import { Box3 } from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

const COLLISION_NAME = /collision/i;

export default function WorldEnvironment({ url, collisionMeshes, worldBounds, environmentScene, onReady, onError }) {
	const { scene } = useThree();
	const asset = useRef(null);

	useEffect(() => {
		let disposed = false;
		let loadedAsset = null;
		const loader = new GLTFLoader();

		loader.load(url, (gltf) => {
			if (disposed) return;
			const collisions = [];
			gltf.scene.traverse((node) => {
				// A "collision" name can land on the mesh itself, or on a
				// parent group wrapping several arbitrarily-named mesh
				// children (a common export pattern) — checking node.isMesh
				// alone missed the latter and silently produced zero
				// collision meshes, leaving nothing to block movement against.
				if (!COLLISION_NAME.test(node.name)) return;
				if (node.isMesh) {
					node.visible = false;
					collisions.push(node);
					return;
				}
				node.traverse((child) => {
					if (child.isMesh) {
						child.visible = false;
						collisions.push(child);
					}
				});
			});
			if (collisions.length === 0) {
				console.warn(`[WorldEnvironment] No collision meshes found in ${url} — nothing named "collision" (case-insensitive) exists in this asset, so movement won't be blocked by anything.`);
			}
			collisionMeshes.current = collisions;
			// The overall modeled extent — used to keep residents (whose spawn
			// position is free-form user input, not something walked into)
			// from ever rendering outside the room, in empty space.
			if (worldBounds) worldBounds.current = new Box3().setFromObject(gltf.scene);
			if (environmentScene) environmentScene.current = gltf.scene;
			scene.add(gltf.scene);
			loadedAsset = gltf.scene;
			asset.current = loadedAsset;
			onReady();
		}, undefined, onError);

		return () => {
			disposed = true;
			collisionMeshes.current = [];
			if (environmentScene) environmentScene.current = null;
			if (loadedAsset) scene.remove(loadedAsset);
		};
	}, [collisionMeshes, worldBounds, environmentScene, onError, onReady, scene, url]);

	return null;
}
