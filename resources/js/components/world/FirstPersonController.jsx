import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { Box3, Vector3 } from 'three';

const SPEED = 3.5;
const PLAYER_HALF_EXTENTS = new Vector3(0.25, 0.9, 0.25);

export default function FirstPersonController({ collisionMeshes, enabled, onPositionChange }) {
	const { camera, gl } = useThree();
	const keys = useRef(new Set());
	const yaw = useRef(0);
	const pitch = useRef(0);
	const lastReportedPosition = useRef(new Vector3());

	useEffect(() => {
		const canvas = gl.domElement;
		const pressedKeys = keys.current;
		const keyDown = (event) => {
			if (['KeyW', 'KeyA', 'KeyS', 'KeyD'].includes(event.code)) pressedKeys.add(event.code);
		};
		const keyUp = (event) => pressedKeys.delete(event.code);
		const mouseMove = (event) => {
			if (document.pointerLockElement !== canvas || !enabled) return;
			yaw.current -= event.movementX * 0.002;
			pitch.current = Math.max(-1.35, Math.min(1.35, pitch.current - event.movementY * 0.002));
			camera.rotation.set(pitch.current, yaw.current, 0, 'YXZ');
		};
		const blur = () => pressedKeys.clear();
		canvas.addEventListener('click', () => enabled && canvas.requestPointerLock());
		window.addEventListener('keydown', keyDown);
		window.addEventListener('keyup', keyUp);
		window.addEventListener('blur', blur);
		document.addEventListener('mousemove', mouseMove);
		return () => {
			pressedKeys.clear();
			window.removeEventListener('keydown', keyDown);
			window.removeEventListener('keyup', keyUp);
			window.removeEventListener('blur', blur);
			document.removeEventListener('mousemove', mouseMove);
			if (document.pointerLockElement === canvas) document.exitPointerLock();
		};
	}, [camera, enabled, gl]);

	useFrame((_, delta) => {
		if (!enabled) return;
		const direction = new Vector3();
		if (keys.current.has('KeyW')) direction.z -= 1;
		if (keys.current.has('KeyS')) direction.z += 1;
		if (keys.current.has('KeyA')) direction.x -= 1;
		if (keys.current.has('KeyD')) direction.x += 1;
		if (direction.lengthSq() > 0) {
			direction.normalize().applyAxisAngle(new Vector3(0, 1, 0), yaw.current).multiplyScalar(SPEED * delta);
			const candidate = camera.position.clone().add(direction);
			const playerBounds = new Box3(candidate.clone().sub(PLAYER_HALF_EXTENTS), candidate.clone().add(PLAYER_HALF_EXTENTS));
			const blocked = collisionMeshes.current.some((mesh) => playerBounds.intersectsBox(new Box3().setFromObject(mesh)));
			if (!blocked) camera.position.copy(candidate);
		}

		if (lastReportedPosition.current.distanceToSquared(camera.position) > 0.05) {
			lastReportedPosition.current.copy(camera.position);
			onPositionChange(camera.position.toArray());
		}
	});

	return null;
}
