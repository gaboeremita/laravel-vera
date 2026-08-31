import { useEffect } from 'react';
import { useThree } from '@react-three/fiber';
import { Vector3 } from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMUtils } from '@pixiv/three-vrm';
import { WorldCollision } from './collisionCheck.js';

export default function WorldEnvironment({ url, onReady, onError }) {
	const { scene } = useThree();

	useEffect(() => {
		let disposed = false;
		let loadedAsset = null;
		let collisionWorld = null;
		const loader = new GLTFLoader();

		loader.load(url, (gltf) => {
			if (disposed) { VRMUtils.deepDispose(gltf.scene); return; }
			loadedAsset = gltf.scene;
			try {
				collisionWorld = new WorldCollision(loadedAsset);
				const center = collisionWorld.bounds.getCenter(new Vector3()).setY(0);
				const spawnPosition = collisionWorld.findSpawn(center);
				if (!spawnPosition) throw new Error('No walkable spawn with enough headroom was found in this environment.');
				scene.add(loadedAsset);
				onReady({ collisionWorld, spawnPosition });
			} catch (error) {
				onError(error);
			}
		}, undefined, (error) => {
			if (!disposed) onError(error);
		});

		return () => {
			disposed = true;
			collisionWorld?.dispose();
			if (loadedAsset) {
				scene.remove(loadedAsset);
				VRMUtils.deepDispose(loadedAsset);
			}
		};
	}, [onError, onReady, scene, url]);

	return null;
}
