import { useEffect, useMemo, useRef, useState } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { AnimationMixer, LoopOnce, LoopRepeat, PositionalAudio } from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMLoaderPlugin, VRMUtils } from '@pixiv/three-vrm';
import { applyBoneQuaternions, captureBoneQuaternions, loadPoseClip } from '../VrmAvatar.jsx';
import { MAX_MOVEMENT_DELTA } from './collisionCheck.js';
import { makeClipInPlace, turnTowardsAngle } from './residentMotion.js';
import { findWorldMotionPose } from './worldMotionPoses.js';

const WALK_SPEED = 0.3;
const WALK_ACCELERATION = 0.18;
const WALK_DECELERATION_SECONDS = 1.2;
const MIN_WALK_SECONDS = 4;
const MAX_WALK_SECONDS = 7;
const MIN_IDLE_SECONDS = 2;
const MAX_IDLE_SECONDS = 4;
const TURN_SPEED = Math.PI * 4;
const LOCOMOTION_BLEND_SECONDS = 0.2;

// Slower than the portrait's own POSE_BLEND_SECONDS (0.25s) — a resident
// has no idle animation to blend back into, so a snappy return read as
// jarring here in a way it doesn't for the portrait.
const POSE_RETURN_SECONDS = 0.6;
// Matches VrmAvatar's EXPRESSION_HOLD_SECONDS — how long a blendshapes-only
// pose (no body animation to ride along with) holds before decaying.
const POSE_EXPRESSION_HOLD_SECONDS = 3.5;
const VOICE_HEIGHT = 1.5;
const VOICE_REF_DISTANCE = 2;
const VOICE_ROLLOFF = 1.2;

export default function ResidentController({ resident, playerPosition, paused, activePose, interaction, collisionWorld, residentPositions, residentVoices, audioListener, inConversation = false }) {
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
	const walkActionRef = useRef(null);
	const walkStartActionRef = useRef(null);
	const walkStopActionRef = useRef(null);
	const defaultActionRef = useRef(null);
	const locomotionActionRef = useRef(null);
	const walkingRef = useRef(false);
	const locomotionPhaseRef = useRef({ name: 'idle', endsAt: 0, velocity: 0, heading: null });
	const [loaded, setLoaded] = useState(false);
	const { x = 0, y = 0, z = 0 } = resident.position ?? {};
	const position = useMemo(() => collisionWorld.findSpawn({ x, y, z }), [collisionWorld, x, y, z]);
	const distance = position ? Math.hypot(playerPosition[0] - position.x, playerPosition[1] - position.y, playerPosition[2] - position.z) : Infinity;
	const walkAnimationUrl = findWorldMotionPose(resident.assistant.poses, 'walk')?.animationUrl ?? null;
	const walkStartAnimationUrl = findWorldMotionPose(resident.assistant.poses, 'walkStart')?.animationUrl ?? null;
	const walkStopAnimationUrl = findWorldMotionPose(resident.assistant.poses, 'walkStop')?.animationUrl ?? null;
	const defaultAnimationUrl = resident.assistant.poses?.find(({ name }) => name?.trim().toLowerCase() === 'default')?.animationUrl ?? null;
	const greetingPose = findWorldMotionPose(resident.assistant.poses, 'greeting');

	useEffect(() => {
		if (loaded || !position || !resident.assistant.vrmUrl) return;
		residentPositions.current.set(resident.id, position);
	}, [loaded, position, resident.id, resident.assistant.vrmUrl, residentPositions]);

	useEffect(() => {
		if (!position) console.warn(`No walkable spawn was found for resident ${resident.id}.`);
	}, [position, resident.id]);

	useEffect(() => {
		if (loaded || !position || distance > 30 || !resident.assistant.vrmUrl) return;
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
			residentPositions.current.set(resident.id, vrm.current.scene.position);

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
	}, [distance, loaded, position, resident.id, resident.assistant.vrmUrl, resident.rotation?.y, residentPositions, scene]);

	useEffect(() => {
		if (!loaded || !residentVoices || !audioListener) return undefined;
		let voice = null;
		const playVoice = async (audioBlob) => {
			const listener = audioListener.current;
			if (!listener || !vrm.current) return null;
			if (listener.context.state === 'suspended') await listener.context.resume();
			const buffer = await listener.context.decodeAudioData(await audioBlob.arrayBuffer());
			if (!voice) {
				voice = new PositionalAudio(listener);
				voice.setRefDistance(VOICE_REF_DISTANCE);
				voice.setRolloffFactor(VOICE_ROLLOFF);
				voice.position.set(0, VOICE_HEIGHT, 0);
				vrm.current.scene.add(voice);
			}
			if (voice.isPlaying) voice.stop();
			voice.setBuffer(buffer);
			voice.play();
			return buffer.duration;
		};
		const voices = residentVoices.current;
		voices.set(resident.id, playVoice);
		return () => {
			voices.delete(resident.id);
			if (voice) {
				if (voice.isPlaying) voice.stop();
				voice.removeFromParent();
			}
		};
	}, [loaded, resident.id, residentVoices, audioListener]);

	useEffect(() => () => {
		residentPositions.current.delete(resident.id);
		if (vrm.current) {
			scene.remove(vrm.current.scene);
			VRMUtils.deepDispose(vrm.current.scene);
		}
		mixer.current?.stopAllAction();
	}, [resident.id, residentPositions, scene]);

	// Preload an explicitly named walk pose, but leave it stopped until the
	// resident actually moves. It shares the resident mixer with conversational
	// poses, so a greeting or other one-shot pose remains the higher priority.
	useEffect(() => {
		if (!loaded || !vrm.current || !walkAnimationUrl) return undefined;
		let cancelled = false;

		void (async () => {
			try {
				const loadedClip = await loadPoseClip(walkAnimationUrl, vrm.current);
				const clip = loadedClip ? makeClipInPlace(loadedClip) : null;
				if (cancelled || !clip || !vrm.current) return;

				const activeMixer = mixer.current ?? new AnimationMixer(vrm.current.scene);
				mixer.current = activeMixer;
				const action = activeMixer.clipAction(clip);
				action.setLoop(LoopRepeat, Infinity);
				walkActionRef.current = action;

				if (walkingRef.current && !posePlayingRef.current && !returnBlendRef.current.active) {
					action.reset().play();
				}
			} catch (error) {
				console.error('[ResidentController] walk animation load error:', error);
			}
		})();

		return () => {
			cancelled = true;
			walkActionRef.current?.stop();
			walkActionRef.current = null;
		};
	}, [loaded, walkAnimationUrl]);

	useEffect(() => {
		if (!loaded || !vrm.current) return undefined;
		let cancelled = false;
		const loadTransition = async (url, actionRef, loop = false) => {
			if (!url) return;
			try {
				const loadedClip = await loadPoseClip(url, vrm.current);
				const clip = loadedClip ? makeClipInPlace(loadedClip) : null;
				if (cancelled || !clip || !vrm.current) return;
				const activeMixer = mixer.current ?? new AnimationMixer(vrm.current.scene);
				mixer.current = activeMixer;
				const action = activeMixer.clipAction(clip);
				action.setLoop(loop ? LoopRepeat : LoopOnce, loop ? Infinity : 1);
				actionRef.current = action;
				if (loop && !locomotionActionRef.current) {
					action.play();
					locomotionActionRef.current = action;
				}
			} catch (error) {
				console.error('[ResidentController] walk transition animation load error:', error);
			}
		};
		void Promise.all([
			loadTransition(walkStartAnimationUrl, walkStartActionRef),
			loadTransition(walkStopAnimationUrl, walkStopActionRef),
			loadTransition(defaultAnimationUrl, defaultActionRef, true),
		]);
		return () => { cancelled = true; };
	}, [defaultAnimationUrl, loaded, walkStartAnimationUrl, walkStopAnimationUrl]);

	// A resident's pose is a one-shot trigger (e.g. a greeting wave), not an
	// ongoing state — its body animation (if any) plays once and eases back
	// to rest (see the 'finished' handler below and the blend loop in
	// useFrame), while its facial blendshapes (if any) ride along with that
	// same window — see the expression handling in useFrame.
	useEffect(() => {
		if (!loaded || !vrm.current) return;
		const triggeredPose = activePose?.residentId === resident.id
			? activePose
			: interaction?.residentId === resident.id
				? { triggerId: interaction.triggerId, animationUrl: greetingPose?.animationUrl, blendshapes: greetingPose?.vrm_blendshapes ?? [] }
				: null;
		if (!triggeredPose || triggeredPose.triggerId === lastPoseTriggerRef.current) return;
		lastPoseTriggerRef.current = triggeredPose.triggerId;

		activeBlendshapesRef.current = triggeredPose.blendshapes ?? [];
		poseExpressionHoldRef.current = 0;
		poseHasAnimationRef.current = !!triggeredPose.animationUrl;

		if (!triggeredPose.animationUrl) {
			// Blendshapes-only pose — no body clip to key the expression's
			// active window off of, so useFrame falls back to a fixed hold.
			posePlayingRef.current = false;
			return;
		}

		posePlayingRef.current = true;
		walkActionRef.current?.stop();
		let cancelled = false;
		(async () => {
			const clip = await loadPoseClip(triggeredPose.animationUrl, vrm.current);
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
	}, [loaded, activePose, greetingPose, interaction, resident.id]);

	useFrame((state, delta) => {
		if (!vrm.current) return;
		const currentPosition = vrm.current.scene.position;
		const currentDistance = Math.hypot(playerPosition[0] - currentPosition.x, playerPosition[1] - currentPosition.y, playerPosition[2] - currentPosition.z);
		let didMove = false;
		const wantsToRoam = resident.behavior === 'roam' && !paused && !inConversation && currentDistance < 30;
		const locomotion = locomotionPhaseRef.current;
		const activateLocomotionAction = (action) => {
			if (!action || locomotionActionRef.current === action) return;
			const previousAction = locomotionActionRef.current;
			action.reset().play();
			if (previousAction) action.crossFadeFrom(previousAction, LOCOMOTION_BLEND_SECONDS, false);
			locomotionActionRef.current = action;
		};
		const playTransition = (actionRef, name) => {
			const action = actionRef.current;
			if (!action) {
				locomotion.name = name === 'starting' ? 'walking' : 'idle';
				if (name === 'stopping') activateLocomotionAction(defaultActionRef.current);
				return;
			}
			activateLocomotionAction(action);
			locomotion.name = name;
			locomotion.endsAt = state.clock.elapsedTime + action.getClip().duration;
		};

		const beginIdle = () => {
			locomotion.name = 'idle';
			locomotion.velocity = 0;
			locomotion.endsAt = state.clock.elapsedTime + MIN_IDLE_SECONDS + Math.random() * (MAX_IDLE_SECONDS - MIN_IDLE_SECONDS);
			activateLocomotionAction(defaultActionRef.current);
		};
		if (!wantsToRoam && ['walking', 'starting', 'turning'].includes(locomotion.name)) {
			playTransition(walkStopActionRef, 'stopping');
			locomotion.velocity = 0;
		}
		if (locomotion.name === 'stopping' && state.clock.elapsedTime >= locomotion.endsAt) beginIdle();
		if (locomotion.name === 'idle') {
			if (locomotion.endsAt === 0) beginIdle();
			if (wantsToRoam && state.clock.elapsedTime >= locomotion.endsAt) {
				locomotion.name = 'turning';
				locomotion.heading = vrm.current.scene.rotation.y + (Math.random() - 0.5) * 1.4;
			}
		}
		if (locomotion.name === 'turning') {
			vrm.current.scene.rotation.y = turnTowardsAngle(vrm.current.scene.rotation.y, locomotion.heading, TURN_SPEED * Math.min(delta, MAX_MOVEMENT_DELTA));
			if (Math.abs(Math.sin(locomotion.heading - vrm.current.scene.rotation.y)) < 0.05) playTransition(walkStartActionRef, 'starting');
		}
		if (locomotion.name === 'starting' && state.clock.elapsedTime >= locomotion.endsAt) {
			locomotion.name = 'walking';
			locomotion.velocity = 0;
			locomotion.endsAt = state.clock.elapsedTime + MIN_WALK_SECONDS + Math.random() * (MAX_WALK_SECONDS - MIN_WALK_SECONDS);
			activateLocomotionAction(walkActionRef.current);
		}
		if (wantsToRoam && locomotion.name === 'walking') {
			const remaining = locomotion.endsAt - state.clock.elapsedTime;
			const targetVelocity = remaining <= WALK_DECELERATION_SECONDS ? WALK_SPEED * Math.max(remaining / WALK_DECELERATION_SECONDS, 0) : WALK_SPEED;
			const velocityChange = WALK_ACCELERATION * Math.min(delta, MAX_MOVEMENT_DELTA);
			locomotion.velocity += Math.sign(targetVelocity - locomotion.velocity) * Math.min(Math.abs(targetVelocity - locomotion.velocity), velocityChange);
			const step = locomotion.velocity * Math.min(delta, MAX_MOVEMENT_DELTA);
			const previousX = currentPosition.x;
			const previousZ = currentPosition.z;
			collisionWorld.move(currentPosition, -Math.sin(vrm.current.scene.rotation.y) * step, -Math.cos(vrm.current.scene.rotation.y) * step);
			const movedX = currentPosition.x - previousX;
			const movedZ = currentPosition.z - previousZ;
			didMove = Math.hypot(movedX, movedZ) > 0.0001;
			if (!didMove || remaining <= 0) playTransition(walkStopActionRef, 'stopping');
		}
		walkingRef.current = didMove;
		if (didMove && !posePlayingRef.current && !returnBlendRef.current.active) {
			activateLocomotionAction(walkActionRef.current);
		}
		if (currentDistance < 30) {
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
