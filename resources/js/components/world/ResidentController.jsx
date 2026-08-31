import { useEffect, useRef, useState } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { AnimationMixer, LoopOnce } from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMLoaderPlugin, VRMUtils } from '@pixiv/three-vrm';
import { applyBoneQuaternions, captureBoneQuaternions, loadPoseClip } from '../VrmAvatar.jsx';
import { ceilingHeight, getGroundHeight } from './groundHeight.js';
import { isPathBlocked } from './collisionCheck.js';
import { clampToBounds } from './clampToBounds.js';

// Slower than the portrait's own POSE_BLEND_SECONDS (0.25s) — a resident
// has no idle animation to blend back into, so a snappy return read as
// jarring here in a way it doesn't for the portrait.
const POSE_RETURN_SECONDS = 0.6;
// Matches VrmAvatar's EXPRESSION_HOLD_SECONDS — how long a blendshapes-only
// pose (no body animation to ride along with) holds before decaying.
const POSE_EXPRESSION_HOLD_SECONDS = 3.5;

export default function ResidentController({ resident, playerPosition, paused, activePose, worldBounds, collisionMeshes, environmentScene }) {
	const { scene } = useThree();
	const vrm = useRef(null);
	const mixer = useRef(null);
	const lastPoseTriggerRef = useRef(null);
	const restPoseRef = useRef(null);
	const returnBlendRef = useRef({ active: false, elapsed: 0, from: null });
	const posePlayingRef = useRef(false);
	const poseHasAnimationRef = useRef(false);
	const poseExpressionHoldRef = useRef(0);
	const activeBlendshapesRef = useRef([]);
	const currentWeightsRef = useRef({});
	const [loaded, setLoaded] = useState(false);
	const rawPosition = resident.position ?? { x: 0, y: 0, z: 0 };
	const clamped = clampToBounds(rawPosition.x, rawPosition.y, rawPosition.z, worldBounds?.current);
	// The configured Y is free-form user input against a coordinate system
	// nobody can see ahead of time — instead of trusting it, rest the
	// resident on the real floor height at its X/Z, same as the player.
	const groundY = getGroundHeight(clamped.x, clamped.z, environmentScene?.current, clamped.y, ceilingHeight(worldBounds?.current));
	const position = { x: clamped.x, y: groundY, z: clamped.z };
	const distance = Math.hypot(playerPosition[0] - position.x, playerPosition[1] - position.y, playerPosition[2] - position.z);

	useEffect(() => {
		if (loaded || distance > 30 || !resident.assistant.vrmUrl) return;
		let cancelled = false;
		const loader = new GLTFLoader();
		loader.register((parser) => new VRMLoaderPlugin(parser));
		loader.load(resident.assistant.vrmUrl, (gltf) => {
			if (cancelled) { VRMUtils.deepDispose(gltf.scene); return; }
			vrm.current = gltf.userData.vrm;
			VRMUtils.rotateVRM0(vrm.current);
			vrm.current.scene.position.set(position.x, position.y, position.z);
			vrm.current.scene.rotation.y = resident.rotation?.y ?? 0;
			scene.add(vrm.current.scene);

			// VRM models load in T-pose; lower the arms to a relaxed stance
			// before capturing it as the rest pose a triggered animation
			// blends back to — same adjustment VrmAvatar.jsx applies to the
			// portrait, otherwise an idle resident stands with arms out.
			const leftUpperArm = vrm.current.humanoid.getNormalizedBoneNode('leftUpperArm');
			const rightUpperArm = vrm.current.humanoid.getNormalizedBoneNode('rightUpperArm');
			if (leftUpperArm) leftUpperArm.rotation.z = 1.2;
			if (rightUpperArm) rightUpperArm.rotation.z = -1.2;

			restPoseRef.current = captureBoneQuaternions(vrm.current);
			setLoaded(true);
		});
		return () => { cancelled = true; };
	}, [distance, loaded, position.x, position.y, position.z, resident.assistant.vrmUrl, resident.rotation?.y, scene]);

	useEffect(() => () => {
		if (vrm.current) {
			scene.remove(vrm.current.scene);
			VRMUtils.deepDispose(vrm.current.scene);
		}
		mixer.current?.stopAllAction();
	}, [scene]);

	// A resident's pose is a one-shot trigger (e.g. a greeting wave), not an
	// ongoing state — its body animation (if any) plays once and eases back
	// to rest (see the 'finished' handler below and the blend loop in
	// useFrame), while its facial blendshapes (if any) ride along with that
	// same window — see the expression handling in useFrame.
	useEffect(() => {
		if (!loaded || !vrm.current) return;
		if (!activePose || activePose.residentId !== resident.id) return;
		if (activePose.triggerId === lastPoseTriggerRef.current) return;
		lastPoseTriggerRef.current = activePose.triggerId;

		activeBlendshapesRef.current = activePose.blendshapes ?? [];
		poseExpressionHoldRef.current = 0;
		poseHasAnimationRef.current = !!activePose.animationUrl;

		if (!activePose.animationUrl) {
			// Blendshapes-only pose — no body clip to key the expression's
			// active window off of, so useFrame falls back to a fixed hold.
			posePlayingRef.current = false;
			return;
		}

		posePlayingRef.current = true;
		let cancelled = false;
		(async () => {
			const clip = await loadPoseClip(activePose.animationUrl, vrm.current);
			if (cancelled || !clip || !vrm.current) { posePlayingRef.current = false; return; }
			if (!mixer.current) mixer.current = new AnimationMixer(vrm.current.scene);

			const action = mixer.current.clipAction(clip);
			action.reset();
			action.setLoop(LoopOnce, 1);
			action.clampWhenFinished = false;
			action.play();

			// With no idle animation loop for world residents (unlike the
			// portrait's default-pose system), holding the clip's last frame
			// forever left long poses visibly stuck mid-gesture once they
			// finished — short poses happened to end close enough to a
			// neutral stance that this went unnoticed. Ease back to the
			// captured rest pose over POSE_RETURN_SECONDS instead of
			// snapping to it instantly (see the blend loop in useFrame).
			const onFinished = (event) => {
				if (event.action !== action) return;
				mixer.current?.removeEventListener('finished', onFinished);
				posePlayingRef.current = false;
				if (restPoseRef.current && vrm.current) {
					returnBlendRef.current = { active: true, elapsed: 0, from: captureBoneQuaternions(vrm.current) };
				}
			};
			mixer.current.addEventListener('finished', onFinished);
		})();
		return () => { cancelled = true; };
	}, [loaded, activePose, resident.id]);

	useFrame((state, delta) => {
		if (!vrm.current) return;
		if (resident.behavior === 'roam' && !paused && distance < 30) {
			const radius = Math.min(Number(resident.behaviorSettings?.radius ?? 1.2), 3);
			const roamed = clampToBounds(
				position.x + Math.sin(state.clock.elapsedTime * 0.55) * radius,
				position.y,
				position.z + Math.cos(state.clock.elapsedTime * 0.55) * radius,
				worldBounds?.current
			);

			// Same raycast-based check FirstPersonController.jsx uses for the
			// player — without it, roaming walked straight through walls and
			// furniture since only the outer room bounds were enforced above.
			const currentGroundY = vrm.current.scene.position.y;
			const roamedGroundY = getGroundHeight(roamed.x, roamed.z, environmentScene?.current, currentGroundY, ceilingHeight(worldBounds?.current));
			const wallBlocked = isPathBlocked(vrm.current.scene.position.x, vrm.current.scene.position.z, roamed.x, roamed.z, currentGroundY, collisionMeshes?.current);

			if (!wallBlocked) {
				vrm.current.scene.position.x = roamed.x;
				vrm.current.scene.position.y = roamedGroundY;
				vrm.current.scene.position.z = roamed.z;
			}
		} else if (distance < 30) {
			// The environment's bounding box/ground height and this VRM load
			// in parallel, so the very first placement (in the load effect
			// below) can land before either is known. Re-asserting the
			// grounded position every frame — cheap, since it's just an
			// assignment — self-corrects once they're ready instead of
			// leaving a stationary resident stuck wherever it first spawned.
			vrm.current.scene.position.x = position.x;
			vrm.current.scene.position.y = position.y;
			vrm.current.scene.position.z = position.z;
		}
		if (distance < 30) {
			mixer.current?.update(delta);

			const blend = returnBlendRef.current;
			if (blend.active && blend.from && restPoseRef.current) {
				blend.elapsed += delta;
				const t = Math.min(blend.elapsed / POSE_RETURN_SECONDS, 1);
				for (const [name, fromQuat] of blend.from) {
					const node = vrm.current.humanoid.getNormalizedBoneNode(name);
					const restQuat = restPoseRef.current.get(name);
					if (!node || !restQuat) continue;
					node.quaternion.slerpQuaternions(fromQuat, restQuat, t);
				}
				if (t >= 1) {
					blend.active = false;
					applyBoneQuaternions(vrm.current, restPoseRef.current);
				}
			}

			// A pose's facial expression rides along with its body animation
			// while that's playing; a blendshapes-only pose instead holds for
			// a fixed duration — same rule VrmAvatar applies to the portrait.
			if (!posePlayingRef.current) poseExpressionHoldRef.current += delta;
			const expressionActive = poseHasAnimationRef.current
				? posePlayingRef.current
				: poseExpressionHoldRef.current < POSE_EXPRESSION_HOLD_SECONDS;
			const targets = expressionActive ? activeBlendshapesRef.current : [];
			const targetMap = Object.fromEntries(targets.filter((t) => t.expression !== 'blink').map((t) => [t.expression, t.weight]));
			const activeExpressions = new Set([...Object.keys(currentWeightsRef.current), ...Object.keys(targetMap)]);
			for (const expr of activeExpressions) {
				const target = targetMap[expr] ?? 0;
				const current = currentWeightsRef.current[expr] ?? 0;
				const lerped = current + (target - current) * Math.min(delta / 0.3, 1);
				currentWeightsRef.current[expr] = lerped;
				vrm.current.expressionManager?.setValue(expr, lerped);
			}

			vrm.current.update(delta);
		}
	});

	return null;
}
