import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { Vector3 } from 'three';
import { MAX_MOVEMENT_DELTA } from './collisionCheck.js';

const SPEED = 3.5;
const EYE_HEIGHT = 1.6;
const UP = new Vector3(0, 1, 0);

export default function FirstPersonController({ collisionWorld, spawnPosition, enabled, onPositionChange }) {
	const { camera, gl } = useThree();
	const keys = useRef(new Set());
	const yaw = useRef(0);
	const pitch = useRef(0);
	const direction = useRef(new Vector3());
	const footPosition = useRef(new Vector3());
	const lastReportedPosition = useRef(new Vector3());
	const spawnedWorld = useRef(null);

	useEffect(() => {
		const canvas = gl.domElement;
		const pressedKeys = keys.current;
		const keyDown = (event) => {
			if (enabled && ['KeyW', 'KeyA', 'KeyS', 'KeyD'].includes(event.code)) pressedKeys.add(event.code);
		};
		const keyUp = (event) => pressedKeys.delete(event.code);
		const mouseMove = (event) => {
			if (document.pointerLockElement !== canvas || !enabled) return;
			yaw.current -= event.movementX * 0.002;
			pitch.current = Math.max(-1.35, Math.min(1.35, pitch.current - event.movementY * 0.002));
			camera.rotation.set(pitch.current, yaw.current, 0, 'YXZ');
		};
		const blur = () => pressedKeys.clear();
		const requestPointerLock = () => { if (enabled) canvas.requestPointerLock(); };
		canvas.addEventListener('click', requestPointerLock);
		window.addEventListener('keydown', keyDown);
		window.addEventListener('keyup', keyUp);
		window.addEventListener('blur', blur);
		document.addEventListener('mousemove', mouseMove);
		return () => {
			pressedKeys.clear();
			canvas.removeEventListener('click', requestPointerLock);
			window.removeEventListener('keydown', keyDown);
			window.removeEventListener('keyup', keyUp);
			window.removeEventListener('blur', blur);
			document.removeEventListener('mousemove', mouseMove);
			if (document.pointerLockElement === canvas) document.exitPointerLock();
		};
	}, [camera, enabled, gl]);

	useFrame(({ camera: activeCamera }, delta) => {
		if (!enabled) return;
		if (spawnedWorld.current !== collisionWorld) {
			spawnedWorld.current = collisionWorld;
			footPosition.current.copy(spawnPosition);
			activeCamera.position.set(spawnPosition.x, spawnPosition.y + EYE_HEIGHT, spawnPosition.z);
			lastReportedPosition.current.copy(activeCamera.position);
			onPositionChange(activeCamera.position.toArray());
		}

		direction.current.set(0, 0, 0);
		if (keys.current.has('KeyW')) direction.current.z -= 1;
		if (keys.current.has('KeyS')) direction.current.z += 1;
		if (keys.current.has('KeyA')) direction.current.x -= 1;
		if (keys.current.has('KeyD')) direction.current.x += 1;

		if (direction.current.lengthSq() > 0) {
			direction.current.normalize().applyAxisAngle(UP, yaw.current).multiplyScalar(SPEED * Math.min(delta, MAX_MOVEMENT_DELTA));
			collisionWorld.move(footPosition.current, direction.current.x, direction.current.z);
			activeCamera.position.set(footPosition.current.x, footPosition.current.y + EYE_HEIGHT, footPosition.current.z);
		}

		if (lastReportedPosition.current.distanceToSquared(activeCamera.position) > 0.05) {
			lastReportedPosition.current.copy(activeCamera.position);
			onPositionChange(activeCamera.position.toArray());
		}
	});

	return null;
}
