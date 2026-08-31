import { useEffect, useRef, useState } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMLoaderPlugin, VRMUtils } from '@pixiv/three-vrm';

export default function ResidentController({ resident, playerPosition, paused }) {
	const { scene } = useThree();
	const vrm = useRef(null);
	const [loaded, setLoaded] = useState(false);
	const position = resident.position ?? { x: 0, y: 0, z: 0 };
	const distance = Math.hypot(playerPosition[0] - position.x, playerPosition[1] - position.y, playerPosition[2] - position.z);

	useEffect(() => {
		if (loaded || distance > 30 || !resident.assistant.vrm_url) return;
		let cancelled = false;
		const loader = new GLTFLoader();
		loader.register((parser) => new VRMLoaderPlugin(parser));
		loader.load(resident.assistant.vrm_url, (gltf) => {
			if (cancelled) { VRMUtils.deepDispose(gltf.scene); return; }
			vrm.current = gltf.userData.vrm;
			VRMUtils.rotateVRM0(vrm.current);
			vrm.current.scene.position.set(position.x, position.y, position.z);
			vrm.current.scene.rotation.y = resident.rotation?.y ?? 0;
			scene.add(vrm.current.scene);
			setLoaded(true);
		});
		return () => { cancelled = true; };
	}, [distance, loaded, position.x, position.y, position.z, resident.assistant.vrm_url, resident.rotation?.y, scene]);

	useEffect(() => () => {
		if (vrm.current) {
			scene.remove(vrm.current.scene);
			VRMUtils.deepDispose(vrm.current.scene);
		}
	}, [scene]);

	useFrame((state, delta) => {
		if (!vrm.current) return;
		if (resident.behavior === 'roam' && !paused && distance < 30) {
			const radius = Math.min(Number(resident.behavior_settings?.radius ?? 1.2), 3);
			vrm.current.scene.position.x = position.x + Math.sin(state.clock.elapsedTime * 0.55) * radius;
			vrm.current.scene.position.z = position.z + Math.cos(state.clock.elapsedTime * 0.55) * radius;
		}
		if (distance < 30) vrm.current.update(delta);
	});

	return null;
}
