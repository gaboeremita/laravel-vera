import { useEffect, useRef } from 'react';
import { useThree } from '@react-three/fiber';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

const COLLISION_NAME = /collision/i;

export default function WorldEnvironment({ url, collisionMeshes, onReady, onError }) {
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
				if (node.isMesh && COLLISION_NAME.test(node.name)) {
					node.visible = false;
					collisions.push(node);
				}
			});
			collisionMeshes.current = collisions;
			scene.add(gltf.scene);
			loadedAsset = gltf.scene;
			asset.current = loadedAsset;
			onReady();
		}, undefined, onError);

		return () => {
			disposed = true;
			collisionMeshes.current = [];
			if (loadedAsset) scene.remove(loadedAsset);
		};
	}, [collisionMeshes, onError, onReady, scene, url]);

	return null;
}
