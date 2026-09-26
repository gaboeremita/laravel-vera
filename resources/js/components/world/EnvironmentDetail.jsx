import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { PLAYER_EYE_HEIGHT } from './collisionCheck.js';
import { floorAt } from './worldLocation.js';
import { LIGHT_BUDGET, nearestLights, visibleFromFloor } from './worldLighting.js';

const UPDATE_SECONDS = 0.3;

/**
 * Keeps the environment cheap to draw: only the lights nearest the user are
 * lit, and groups the world file tags with the floors they can be seen from
 * are hidden from every other floor.
 */
export default function EnvironmentDetail({ root, layout, playerState }) {
	const { camera } = useThree();
	const parts = useRef({ lights: [], positions: [], tagged: [], elapsed: UPDATE_SECONDS });

	useEffect(() => {
		const lights = [];
		const tagged = [];
		root.updateMatrixWorld(true);
		root.traverse((node) => {
			if (node.isLight && !node.isAmbientLight && !node.isHemisphereLight) lights.push(node);
			if (Array.isArray(node.userData?.visibleFrom)) tagged.push(node);
		});
		parts.current = { lights, positions: lights.map((light) => light.getWorldPosition(light.position.clone())), tagged, elapsed: UPDATE_SECONDS };
		return () => {
			for (const node of [...lights, ...tagged]) node.visible = true;
		};
	}, [root]);

	useFrame((_, delta) => {
		const current = parts.current;
		current.elapsed += delta;
		if (current.elapsed < UPDATE_SECONDS) return;
		current.elapsed = 0;
		const foot = playerState?.current?.footPosition ?? { x: camera.position.x, y: camera.position.y - PLAYER_EYE_HEIGHT, z: camera.position.z };

		if (current.lights.length > LIGHT_BUDGET) {
			const lit = new Set(nearestLights(current.positions, foot));
			current.lights.forEach((light, index) => { light.visible = lit.has(index); });
		}

		const floorId = floorAt(layout, foot.y)?.id ?? null;
		// eslint-disable-next-line react-hooks/immutability
		for (const node of current.tagged) node.visible = visibleFromFloor(node.userData.visibleFrom, floorId);
	});

	return null;
}
