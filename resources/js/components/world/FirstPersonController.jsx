import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { Vector3 } from 'three';
import { ceilingHeight, getGroundHeight } from './groundHeight.js';
import { isPathBlocked } from './collisionCheck.js';
import { clampToBounds } from './clampToBounds.js';

const SPEED = 3.5;
const EYE_HEIGHT = 1.6;

export default function FirstPersonController({ collisionMeshes, environmentScene, worldBounds, enabled, onPositionChange }) {
	const { camera, gl } = useThree();
	const keys = useRef(new Set());
	const yaw = useRef(0);
	const pitch = useRef(0);
	const lastReportedPosition = useRef(new Vector3());
	const hasSpawnedRef = useRef(false);

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

		// There's no configured spawn point anywhere in this app — the camera
		// always started at a hardcoded [0, 1.6, 4], which had no relation to
		// any given room's actual footprint and could easily land outside it.
		// That went unnoticed while nothing blocked walking back in; once
		// wall collision actually worked, spawning outside became a real
		// dead end. Use the room's own bounding-box center instead, once it's
		// known — a generic but geometry-derived guess, not a fixed one.
		if (!hasSpawnedRef.current && worldBounds?.current && !worldBounds.current.isEmpty()) {
			hasSpawnedRef.current = true;
			const center = worldBounds.current.getCenter(new Vector3());
			const spawnGroundY = getGroundHeight(center.x, center.z, environmentScene.current, 0, ceilingHeight(worldBounds.current));
			camera.position.set(center.x, spawnGroundY + EYE_HEIGHT, center.z);
		}

		const direction = new Vector3();
		if (keys.current.has('KeyW')) direction.z -= 1;
		if (keys.current.has('KeyS')) direction.z += 1;
		if (keys.current.has('KeyA')) direction.x -= 1;
		if (keys.current.has('KeyD')) direction.x += 1;

		if (direction.lengthSq() > 0) {
			direction.normalize().applyAxisAngle(new Vector3(0, 1, 0), yaw.current).multiplyScalar(SPEED * delta);

			// The room's GLB has no collision/floor geometry authored into it
			// at all (confirmed by WorldEnvironment.jsx's own console warning
			// once collisionMeshes comes back empty) — a step-height guard
			// meant to catch climbing onto disconnected geometry like a roof
			// ended up misfiring on ordinary floor-height differences at a
			// doorway threshold instead, since there's no real wall data to
			// distinguish "roof" from "next room" by. Clamping horizontally
			// to the room's own bounding box is the containment that's
			// actually available here — same as residents already get.
			const clamped = clampToBounds(camera.position.x + direction.x, 0, camera.position.z + direction.z, worldBounds?.current);
			const candidateX = clamped.x;
			const candidateZ = clamped.z;
			const groundY = getGroundHeight(candidateX, candidateZ, environmentScene.current, camera.position.y - EYE_HEIGHT, ceilingHeight(worldBounds?.current));
			const candidateY = groundY + EYE_HEIGHT;

			const wallBlocked = isPathBlocked(camera.position.x, camera.position.z, candidateX, candidateZ, groundY, collisionMeshes.current);
			if (!wallBlocked) camera.position.set(candidateX, candidateY, candidateZ);
		} else {
			// Standing still doesn't skip grounding — nothing here simulates
			// falling, so without re-checking every frame the camera stays
			// wherever it last was even if that's floating above or sunk
			// into the actual floor (e.g. right after spawning).
			const groundY = getGroundHeight(camera.position.x, camera.position.z, environmentScene.current, camera.position.y - EYE_HEIGHT, ceilingHeight(worldBounds?.current));
			camera.position.setY(groundY + EYE_HEIGHT);
		}

		if (lastReportedPosition.current.distanceToSquared(camera.position) > 0.05) {
			lastReportedPosition.current.copy(camera.position);
			onPositionChange(camera.position.toArray());
		}
	});

	return null;
}
