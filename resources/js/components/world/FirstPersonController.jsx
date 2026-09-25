import { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { Vector3 } from 'three';
import { MAX_MOVEMENT_DELTA, PLAYER_EYE_HEIGHT } from './collisionCheck.js';
import { isTypingTarget } from './keyboardFocus.js';
import { getGroundHeight } from './groundHeight.js';
import { GRAVITY, canJump, eyeHeightFor, jumpVelocity, landingDip, movementSpeed, nextMovementMode, swimEyeY, targetFov } from './playerMotion.js';
import { playJump, playLanding } from './worldSounds.js';
import { clampLook, postureView, yawForDirection } from './playerPostures.js';

const UP = new Vector3(0, 1, 0);
const MOVEMENT_KEYS = ['KeyW', 'KeyA', 'KeyS', 'KeyD'];
const RUN_KEYS = ['ShiftLeft', 'ShiftRight'];
const GLIDE_SECONDS = 0.8;
const FACE_SECONDS = 0.4;
const EYE_EASE_RATE = 12;
const FOV_EASE_RATE = 6;
const MIN_SWIM_EYE_ABOVE_SURFACE = 0.1;
const MAX_PITCH = 1.35;
const DIP_RECOVERY_RATE = 8;

function playSound(play, ...args) {
	try {
		play(...args);
	} catch (error) {
		console.error('[FirstPersonController] could not play a sound', error);
	}
}

function easeInOut(t) {
	return t < 0.5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2;
}

function lerpAngle(from, to, t) {
	return from + Math.atan2(Math.sin(to - from), Math.cos(to - from)) * t;
}

export default function FirstPersonController({ collisionWorld, spawnPosition, enabled, onPositionChange, playerState: playerStateRef, playerCommands: playerCommandsRef, onMovementChange, onGetUpIntent, onMoveIntent }) {
	const { camera, gl } = useThree();
	const keys = useRef(new Set());
	const runHeld = useRef(false);
	const crouchToggled = useRef(false);
	const yaw = useRef(0);
	const pitch = useRef(0);
	const direction = useRef(new Vector3());
	const footPosition = useRef(new Vector3());
	const lastReportedFoot = useRef(new Vector3());
	const spawnedWorld = useRef(null);
	const mode = useRef('walking');
	const eyeOffset = useRef(PLAYER_EYE_HEIGHT);
	const elapsed = useRef(0);
	const reducedMotion = useRef(false);
	const seat = useRef(null);
	const glide = useRef(null);
	const activity = useRef({ spotId: null, activityId: null });
	const airborne = useRef(null);
	const jumpRequested = useRef(false);
	const dip = useRef(0);
	const callbacks = useRef({});

	useEffect(() => {
		callbacks.current = { onPositionChange, onMovementChange, onGetUpIntent, onMoveIntent };
	});

	useEffect(() => {
		const query = window.matchMedia('(prefers-reduced-motion: reduce)');
		reducedMotion.current = query.matches;
		const change = (event) => { reducedMotion.current = event.matches; };
		query.addEventListener('change', change);
		return () => query.removeEventListener('change', change);
	}, []);

	useEffect(() => {
		if (!playerStateRef) return;
		playerStateRef.current = { footPosition: { x: 0, y: 0, z: 0 }, movement: 'walking', posture: 'standing', spotId: null, activityId: null, approach: null };
	}, [playerStateRef]);

	useEffect(() => {
		if (!playerCommandsRef) return undefined;
		const startGlide = ({ toEye, toYaw, toPitch, duration }) => new Promise((resolve) => {
			glide.current = {
				fromEye: camera.position.clone(),
				toEye: new Vector3(toEye.x, toEye.y, toEye.z),
				fromYaw: yaw.current,
				toYaw,
				fromPitch: pitch.current,
				toPitch,
				elapsed: 0,
				duration,
				resolve,
			};
		});

		playerCommandsRef.current = {
			settleOnSpot: async ({ spot, posture }) => {
				const view = postureView({ spot, posture });
				// Spot approach points sit at seat height and may face a desk, so
				// the floor the user stood on to choose the activity is the one
				// sure place to stand back up on.
				const exitFoot = footPosition.current.clone();
				if (airborne.current) exitFoot.y = getGroundHeight(exitFoot.x, exitFoot.z, collisionWorld.octree, exitFoot.y - 4, exitFoot.y) ?? exitFoot.y;
				keys.current.clear();
				crouchToggled.current = false;
				airborne.current = null;
				seat.current = { spot, posture, view, exitFoot };
				activity.current.spotId = spot.id;
				footPosition.current.copy(exitFoot);
				await startGlide({ toEye: view.eye, toYaw: view.yaw, toPitch: view.pitch, duration: GLIDE_SECONDS });
			},
			getUp: async () => {
				const held = seat.current;
				if (!held) return;
				await startGlide({ toEye: { x: held.exitFoot.x, y: held.exitFoot.y + PLAYER_EYE_HEIGHT, z: held.exitFoot.z }, toYaw: yaw.current, toPitch: 0, duration: GLIDE_SECONDS });
				seat.current = null;
				activity.current = { spotId: null, activityId: null };
				mode.current = 'walking';
				eyeOffset.current = PLAYER_EYE_HEIGHT;
				footPosition.current.copy(held.exitFoot);
			},
			faceToward: async (point) => {
				const toYaw = yawForDirection(point.x - footPosition.current.x, point.z - footPosition.current.z);
				await startGlide({ toEye: camera.position, toYaw, toPitch: pitch.current, duration: FACE_SECONDS });
			},
			setActivity: ({ spotId = null, activityId = null } = {}) => {
				activity.current = { spotId, activityId };
			},
		};
		return () => { playerCommandsRef.current = null; };
	}, [camera, collisionWorld, playerCommandsRef]);

	useEffect(() => {
		const canvas = gl.domElement;
		const pressedKeys = keys.current;
		const keyDown = (event) => {
			if (!enabled || isTypingTarget(event.target)) return;
			const isMovement = MOVEMENT_KEYS.includes(event.code);
			const isRun = RUN_KEYS.includes(event.code);
			if (seat.current) {
				if (isMovement || isRun || event.code === 'Space') {
					event.preventDefault();
					if (!glide.current) callbacks.current.onGetUpIntent?.();
				}
				return;
			}
			if (isMovement) {
				pressedKeys.add(event.code);
				if (activity.current.activityId) callbacks.current.onMoveIntent?.();
			}
			if (isRun) runHeld.current = true;
			if (event.code === 'Space') {
				event.preventDefault();
				if (!event.repeat) {
					jumpRequested.current = true;
					if (activity.current.activityId) callbacks.current.onMoveIntent?.();
				}
			}
			if (event.code === 'KeyQ' && !event.repeat && mode.current !== 'swimming') crouchToggled.current = !crouchToggled.current;
		};
		const keyUp = (event) => {
			pressedKeys.delete(event.code);
			if (RUN_KEYS.includes(event.code)) runHeld.current = false;
		};
		const mouseMove = (event) => {
			if (document.pointerLockElement !== canvas || !enabled || glide.current) return;
			let nextYaw = yaw.current - event.movementX * 0.002;
			let nextPitch = Math.max(-MAX_PITCH, Math.min(MAX_PITCH, pitch.current - event.movementY * 0.002));
			if (seat.current) ({ yaw: nextYaw, pitch: nextPitch } = clampLook({ yaw: nextYaw, pitch: nextPitch }, seat.current.view));
			yaw.current = nextYaw;
			pitch.current = nextPitch;
			camera.rotation.set(pitch.current, yaw.current, 0, 'YXZ');
		};
		const blur = () => {
			pressedKeys.clear();
			runHeld.current = false;
		};
		const focusIn = (event) => { if (isTypingTarget(event.target)) blur(); };
		const requestPointerLock = () => { if (enabled) canvas.requestPointerLock(); };
		canvas.addEventListener('click', requestPointerLock);
		window.addEventListener('keydown', keyDown);
		window.addEventListener('keyup', keyUp);
		window.addEventListener('blur', blur);
		document.addEventListener('focusin', focusIn);
		document.addEventListener('mousemove', mouseMove);
		return () => {
			pressedKeys.clear();
			canvas.removeEventListener('click', requestPointerLock);
			window.removeEventListener('keydown', keyDown);
			window.removeEventListener('keyup', keyUp);
			window.removeEventListener('blur', blur);
			document.removeEventListener('focusin', focusIn);
			document.removeEventListener('mousemove', mouseMove);
			if (document.pointerLockElement === canvas) document.exitPointerLock();
		};
	}, [camera, enabled, gl]);

	const writePlayerState = () => {
		if (!playerStateRef?.current) return;
		playerStateRef.current = {
			footPosition: { x: footPosition.current.x, y: footPosition.current.y, z: footPosition.current.z },
			movement: mode.current,
			posture: seat.current?.posture ?? (mode.current === 'swimming' || mode.current === 'crouching' ? mode.current : 'standing'),
			spotId: activity.current.spotId,
			activityId: activity.current.activityId,
			approach: seat.current ? { x: seat.current.exitFoot.x, y: seat.current.exitFoot.y, z: seat.current.exitFoot.z } : null,
		};
	};

	const reportPosition = () => {
		if (lastReportedFoot.current.distanceToSquared(footPosition.current) <= 0.05) return;
		lastReportedFoot.current.copy(footPosition.current);
		callbacks.current.onPositionChange?.([footPosition.current.x, footPosition.current.y + PLAYER_EYE_HEIGHT, footPosition.current.z]);
	};

	useFrame(({ camera: activeCamera }, delta) => {
		if (!enabled) return;
		const step = Math.min(delta, MAX_MOVEMENT_DELTA);
		elapsed.current += delta;

		if (spawnedWorld.current !== collisionWorld) {
			spawnedWorld.current = collisionWorld;
			footPosition.current.copy(spawnPosition);
			activeCamera.position.set(spawnPosition.x, spawnPosition.y + PLAYER_EYE_HEIGHT, spawnPosition.z);
			lastReportedFoot.current.copy(footPosition.current);
			callbacks.current.onPositionChange?.([spawnPosition.x, spawnPosition.y + PLAYER_EYE_HEIGHT, spawnPosition.z]);
		}

		const activeGlide = glide.current;
		if (activeGlide) {
			activeGlide.elapsed += delta;
			const progress = Math.min(1, activeGlide.elapsed / activeGlide.duration);
			const eased = easeInOut(progress);
			activeCamera.position.lerpVectors(activeGlide.fromEye, activeGlide.toEye, eased);
			yaw.current = lerpAngle(activeGlide.fromYaw, activeGlide.toYaw, eased);
			pitch.current = activeGlide.fromPitch + (activeGlide.toPitch - activeGlide.fromPitch) * eased;
			activeCamera.rotation.set(pitch.current, yaw.current, 0, 'YXZ');
			if (progress >= 1) {
				glide.current = null;
				activeGlide.resolve();
			}
			writePlayerState();
			reportPosition();
			return;
		}

		if (seat.current) {
			writePlayerState();
			reportPosition();
			return;
		}

		const foot = footPosition.current;
		const surfaceY = collisionWorld.waterSurfaceAbove(foot.x, foot.z, foot.y);
		const waterDepth = surfaceY === null ? 0 : surfaceY - foot.y;
		const setMode = (nextMode) => {
			if (nextMode === mode.current) return;
			mode.current = nextMode;
			callbacks.current.onMovementChange?.(nextMode);
		};
		if (runHeld.current) crouchToggled.current = false;
		if (!airborne.current) {
			const nextMode = nextMovementMode({ current: mode.current, runHeld: runHeld.current, crouchToggled: crouchToggled.current, waterDepth, moving: keys.current.size > 0 });
			if (nextMode === 'swimming') crouchToggled.current = false;
			setMode(nextMode);
		}

		if (jumpRequested.current) {
			jumpRequested.current = false;
			if (canJump({ mode: mode.current, seated: false, airborne: Boolean(airborne.current) })) {
				crouchToggled.current = false;
				if (mode.current === 'crouching') setMode('walking');
				airborne.current = { velocityY: jumpVelocity() };
				playSound(playJump);
			}
		}

		direction.current.set(0, 0, 0);
		if (keys.current.has('KeyW')) direction.current.z -= 1;
		if (keys.current.has('KeyS')) direction.current.z += 1;
		if (keys.current.has('KeyA')) direction.current.x -= 1;
		if (keys.current.has('KeyD')) direction.current.x += 1;
		if (direction.current.lengthSq() > 0) direction.current.normalize().applyAxisAngle(UP, yaw.current).multiplyScalar(movementSpeed(mode.current, runHeld.current));

		if (airborne.current) {
			airborne.current.velocityY -= GRAVITY * step;
			const velocity = { x: direction.current.x, y: airborne.current.velocityY, z: direction.current.z };
			const { landed } = collisionWorld.airStep(foot, velocity, step);
			airborne.current.velocityY = velocity.y;
			if (landed) {
				const fallSpeed = airborne.current.velocityY;
				airborne.current = null;
				dip.current = landingDip(fallSpeed, reducedMotion.current);
				playSound(playLanding, fallSpeed);
			}
		} else if (direction.current.lengthSq() > 0) {
			const result = collisionWorld.move(foot, direction.current.x * step, direction.current.z * step, { canFall: mode.current !== 'swimming' });
			if (result === 'falling') airborne.current = { velocityY: 0 };
		}

		// The swimming body keeps walking along the pool floor so walls, steps
		// and objects in the water collide exactly as on land; only the view
		// rises to the surface.
		const targetEye = mode.current === 'swimming' && surfaceY !== null
			? swimEyeY(surfaceY, elapsed.current, reducedMotion.current) - foot.y
			: eyeHeightFor(mode.current);
		eyeOffset.current += (targetEye - eyeOffset.current) * (1 - Math.exp(-delta * EYE_EASE_RATE));
		dip.current *= Math.exp(-delta * DIP_RECOVERY_RATE);
		let eyeY = foot.y + eyeOffset.current - dip.current;
		if (mode.current === 'swimming' && surfaceY !== null) eyeY = Math.max(eyeY, surfaceY + MIN_SWIM_EYE_ABOVE_SURFACE);
		activeCamera.position.set(foot.x, eyeY, foot.z);

		const fov = targetFov(mode.current, reducedMotion.current);
		if (Math.abs(activeCamera.fov - fov) > 0.01) {
			activeCamera.fov += (fov - activeCamera.fov) * (1 - Math.exp(-delta * FOV_EASE_RATE));
			activeCamera.updateProjectionMatrix();
		}

		writePlayerState();
		reportPosition();
	});

	return null;
}
