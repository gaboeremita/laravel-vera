import { useRef, useEffect, useCallback, useState } from 'react';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { AnimationMixer, Box3, BackSide, LoopOnce, LoopRepeat, RepeatWrapping, SRGBColorSpace, TextureLoader, Vector3 } from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { FBXLoader } from 'three/addons/loaders/FBXLoader.js';
import { VRMLoaderPlugin, VRMUtils } from '@pixiv/three-vrm';
import { VRMAnimationLoaderPlugin, createVRMAnimationClip } from '@pixiv/three-vrm-animation';
import veraAvatar from '../../images/vera-avatar.png';
import defaultFloor from '../../images/avatar-background-default-floor.png';
import defaultSurroundings from '../../images/avatar-background-default-surroundings.png';
import useAvatarBackground from '../hooks/useAvatarBackground.js';
import { retargetMixamoAnimation } from '../utils/mixamoRetargeting.js';

const EXPRESSION_HOLD_SECONDS = 3.5;
const BACKGROUND_FADE_SECONDS = 0.4;
const POSE_BLEND_SECONDS = 0.25;
// Mixamo's single-frame "pose" exports (as opposed to multi-second motion
// clips) clock in at ~0.033s (one frame, padded to 2 identical keyframes) —
// under LoopOnce that fires 'finished' almost instantly, so without an
// explicit hold the body reverts before it's even visible. Real animations
// in practice run several seconds at minimum, so this threshold has a wide
// margin. The hold reuses EXPRESSION_HOLD_SECONDS so a static pose's body
// and (if it has one) facial expression revert together.
const POSE_STATIC_CLIP_SECONDS = 0.5;

// vrm.humanoid.humanBones returns the *raw* bones — a different node than
// the *normalized* ones AnimationMixer clips actually target (built via
// getNormalizedBoneNode() in both createVRMAnimationClip and
// mixamoRetargeting.js) and that vrm.update() re-derives raw bones from
// every frame. Reading/writing the raw set here would silently do nothing
// (or get immediately overwritten) — every bone lookup in this file must go
// through getNormalizedBoneNode() to touch the nodes actually being driven.
function captureBoneQuaternions(vrm) {
	const map = new Map();
	for (const name of Object.keys(vrm.humanoid.humanBones)) {
		const node = vrm.humanoid.getNormalizedBoneNode(name);
		if (node) map.set(name, node.quaternion.clone());
	}
	return map;
}

function applyBoneQuaternions(vrm, quatMap) {
	for (const [name, quat] of quatMap) {
		const node = vrm.humanoid.getNormalizedBoneNode(name);
		if (node) node.quaternion.copy(quat);
	}
}

// Shared by the one-shot triggered-pose loader and the looping default-pose
// loader — parses a .vrma or .fbx animation URL into a THREE.AnimationClip
// targeting this vrm's normalized bones.
async function loadPoseClip(url, vrm) {
	if (url.toLowerCase().endsWith('.fbx')) {
		const fbxAsset = await new FBXLoader().loadAsync(url);
		return retargetMixamoAnimation(fbxAsset, vrm);
	}

	const loader = new GLTFLoader();
	loader.register((parser) => new VRMAnimationLoaderPlugin(parser));
	const gltf = await loader.loadAsync(url);
	const vrmAnimation = gltf.userData.vrmAnimations?.[0];
	return vrmAnimation ? createVRMAnimationClip(vrmAnimation, vrm) : null;
}

// The backdrop is a *partial* cylinder arc, not a full 360° wrap — wrapping a
// single non-tileable image around a full circumference stretches it by
// (2*pi*radius / height) versus its native aspect ratio, which reads as
// nonsense distortion rather than a scene. Sizing the visible arc so its
// width (radius * thetaLength) roughly matches a typical generated image's
// aspect ratio against BACKDROP_HEIGHT keeps the image close to undistorted.
const BACKDROP_RADIUS = 3;
// Past ~5 at this radius the mesh extends beyond anything the camera can
// see — the floor clips y<0, and the 30° vertical fov only reaches ~3.65
// above camera height (~1.3) — so extra height just stretches the texture
// over unseen surface, magnifying the visible crop. 3 landed well within
// that by eye.
const BACKDROP_HEIGHT = 3;
// Matches the backdrop's radius exactly (half-extent = BACKDROP_RADIUS) so
// the floor's edge always meets the base of the backdrop wall with no gap,
// regardless of how BACKDROP_RADIUS gets tuned.
const FLOOR_SIZE = BACKDROP_RADIUS * 2;
// Arc width tracks the 3:2 the generator is asked for, so the image maps on
// undistorted rather than being squeezed to fit an unrelated arc.
const BACKDROP_THETA_LENGTH = (BACKDROP_HEIGHT * 1.5) / BACKDROP_RADIUS;
// CylinderGeometry places theta=0 at +Z, sweeping toward +X as theta
// increases (x = r*sin(theta), z = r*cos(theta)) — the camera looks toward
// -Z, so the wall it actually sees is centered on theta = PI.
const BACKDROP_THETA_START = Math.PI - BACKDROP_THETA_LENGTH / 2;
// CylinderGeometry is centered on its own local origin (spans -height/2 to
// +height/2), so a mesh position of BACKDROP_HEIGHT / 2 puts its *bottom*
// at world y=0 — floor level — and it rises upward from there, instead of
// straddling the floor and having its lower half hidden beneath it.
const BACKDROP_Y = BACKDROP_HEIGHT / 2;

function VrmScene({ vrmUrl, emotion, blendshapes, poseBlendshapes, poseAnimationUrl, poseTriggerId, defaultPoseBlendshapes, defaultPoseAnimationUrl, onLoaded, onError }) {
	const { scene, camera } = useThree();
	const vrmRef = useRef(null);
	const currentWeightsRef = useRef({});
	const blinkRef = useRef({ phase: 'waiting', phaseElapsed: 0, threshold: 3 });
	const elapsedRef = useRef(0);
	const lastEmotionRef = useRef(emotion);
	const expressionHoldRef = useRef(0);
	const lastPoseTriggerRef = useRef(poseTriggerId);
	const poseExpressionHoldRef = useRef(0);
	const mixerRef = useRef(null);
	const poseActionRef = useRef(null);
	const posePlayingRef = useRef(false);
	const loadedPoseTriggerRef = useRef(null);
	const restPoseRef = useRef(null);
	const poseBlendRef = useRef({ active: false, mode: null, elapsed: 0 });
	const poseBlendFromRef = useRef(null);
	const poseClipDurationRef = useRef(0);
	const poseHoldRemainingRef = useRef(0);
	const pendingPoseTransitionRef = useRef(null);

	// The default pose's animation loops continuously as the idle baseline
	// whenever no triggered pose is playing — a real pose interrupts it and
	// it resumes once that pose finishes. defaultPoseAnimationUrlRef always
	// holds the latest prop value (kept current every render, not just on
	// change) so the async VRM-load callback can start the loop immediately
	// once loading finishes, without waiting for a second prop change.
	const defaultActionRef = useRef(null);
	const defaultLoopActiveRef = useRef(false);
	const loadedDefaultUrlRef = useRef(null);
	const defaultPoseAnimationUrlRef = useRef(defaultPoseAnimationUrl);
	useEffect(() => {
		defaultPoseAnimationUrlRef.current = defaultPoseAnimationUrl;
	}, [defaultPoseAnimationUrl]);

	// Creates the mixer and its single 'finished' listener on first use,
	// whichever of the triggered-pose loader or the default-loop loader gets
	// there first. 'finished' only ever fires for LoopOnce actions, so this
	// only ever reacts to a triggered pose completing — it hands off to
	// resuming the default loop (if one is configured) instead of blending
	// to the static rest pose, whenever one is available.
	const ensureMixer = useCallback((vrm) => {
		if (mixerRef.current) return mixerRef.current;

		const mixer = new AnimationMixer(vrm.scene);
		mixer.addEventListener('finished', (event) => {
			const startTransition = () => {
				poseBlendFromRef.current = captureBoneQuaternions(vrm);
				event.action.stop();

				if (defaultActionRef.current && defaultPoseAnimationUrlRef.current) {
					defaultActionRef.current.reset().play();
					defaultLoopActiveRef.current = true;
					// resumingDefault: this 'in' blend is handing off from a
					// finished trigger back to the default loop, not blending
					// into a fresh trigger — posePlayingRef must clear once it
					// completes, or it'd stay stuck true forever and permanently
					// block idle sway if the default animation is later removed.
					poseBlendRef.current = { active: true, mode: 'in', elapsed: 0, resumingDefault: true };
				} else {
					poseBlendRef.current = { active: true, mode: 'out', elapsed: 0 };
				}
			};

			// A single-frame "pose" clip (e.g. a Mixamo pose export) fires
			// 'finished' almost instantly under LoopOnce — hold it for a beat
			// before reverting instead of transitioning away the moment it's
			// barely visible. Deliberately leave the action running (not
			// .stop()'d) for the hold duration — with clampWhenFinished, the
			// mixer keeps re-asserting the clip's true final frame on every
			// update() call on its own, which is more reliable than manually
			// freezing a one-time captured snapshot (the entry blend may not
			// have fully converged onto the mixer's live output yet at the
			// instant 'finished' fires for such a short clip). A real
			// multi-second animation already had its own natural duration, so
			// it transitions away immediately once it's actually done, same
			// as before.
			if (poseClipDurationRef.current < POSE_STATIC_CLIP_SECONDS) {
				poseHoldRemainingRef.current = EXPRESSION_HOLD_SECONDS;
				pendingPoseTransitionRef.current = startTransition;
			} else {
				startTransition();
			}
		});
		mixerRef.current = mixer;
		return mixer;
	}, []);

	// Loads (if not already loaded) and plays the default pose's animation
	// on a continuous loop — the idle baseline whenever no pose is currently
	// triggered. No-ops when no default animation is configured.
	const startDefaultLoop = useCallback(
		async (vrm, url) => {
			if (!url || loadedDefaultUrlRef.current === url) return;

			let clip = null;
			try {
				clip = await loadPoseClip(url, vrm);
			} catch (error) {
				console.error('[VrmAvatar] default pose animation load error:', error);
			}

			// The prop may have changed again while this was loading.
			if (!clip || defaultPoseAnimationUrlRef.current !== url || vrmRef.current !== vrm) return;

			loadedDefaultUrlRef.current = url;
			const mixer = ensureMixer(vrm);

			// Only take over as the active idle animation right away if
			// nothing is currently mid-trigger — otherwise the running
			// trigger's 'finished' handler picks this up naturally once it
			// completes, instead of yanking control away mid-pose. Capture
			// the "from" pose before the new action starts writing to bones,
			// and blend in the same way a triggered pose does, so the very
			// first activation doesn't snap from the static rest stance.
			const takesOverNow = !posePlayingRef.current;
			if (takesOverNow) {
				poseBlendFromRef.current = captureBoneQuaternions(vrm);
			}

			defaultActionRef.current?.stop();
			const action = mixer.clipAction(clip);
			action.setLoop(LoopRepeat, Infinity);
			defaultActionRef.current = action;

			if (takesOverNow) {
				// Safe to actually start playing: nothing else is currently
				// driving these bones. When a pose IS mid-trigger, the action
				// is left unplayed (clipAction() alone doesn't touch any
				// bones) — playing it now would fight the trigger's action on
				// the same bones, since the mixer sums same-property actions
				// rather than one cleanly overriding the other. The
				// 'finished' handler starts it with .reset().play() instead,
				// once the trigger is done and it's safe to take over.
				action.play();
				defaultLoopActiveRef.current = true;
				poseBlendRef.current = { active: true, mode: 'in', elapsed: 0, resumingDefault: false };
			}
		},
		[ensureMixer]
	);

	useEffect(() => {
		let cancelled = false;
		const loader = new GLTFLoader();
		loader.register((parser) => new VRMLoaderPlugin(parser));

		loader.load(
			vrmUrl,
			(gltf) => {
				if (cancelled) {
					VRMUtils.deepDispose(gltf.scene);
					return;
				}
				const vrm = gltf.userData.vrm;
				VRMUtils.rotateVRM0(vrm);
				vrmRef.current = vrm;
				scene.add(vrm.scene);

				// VRM models load in T-pose; lower the arms to a relaxed stance.
				const leftUpperArm = vrm.humanoid.getNormalizedBoneNode('leftUpperArm');
				const rightUpperArm = vrm.humanoid.getNormalizedBoneNode('rightUpperArm');
				if (leftUpperArm) leftUpperArm.rotation.z = 1.2;
				if (rightUpperArm) rightUpperArm.rotation.z = -1.2;

				// Snapshot this relaxed stance as the pose a triggered animation
				// blends back to once it finishes, so the body doesn't stay
				// frozen on the clip's last frame (AnimationMixer with
				// clampWhenFinished otherwise just holds there indefinitely).
				restPoseRef.current = captureBoneQuaternions(vrm);

				if (defaultPoseAnimationUrlRef.current) {
					void startDefaultLoop(vrm, defaultPoseAnimationUrlRef.current);
				}

				// Frame from the thighs up: fit the vertical range from the hips
				// bone (top of the thighs, standard humanoid skeleton) to the
				// top of the head into the vertical fov, so the crop line lands
				// in the same place regardless of the model's own proportions.
				vrm.scene.updateWorldMatrix(true, true);
				const box = new Box3().setFromObject(vrm.scene);
				const center = box.getCenter(new Vector3());
				const hips = vrm.humanoid.getNormalizedBoneNode('hips');
				const hipsPosition = new Vector3();
				if (hips) hips.getWorldPosition(hipsPosition);
				const frameBottom = hips ? hipsPosition.y : box.min.y;
				const frameTop = box.max.y;
				const frameHeight = frameTop - frameBottom;
				const frameCenterY = (frameTop + frameBottom) / 2;
				const fovRad = (camera.fov * Math.PI) / 180;
				const distance = (frameHeight / 2) / Math.tan(fovRad / 2) * 1.1;
				camera.position.set(center.x, frameCenterY, center.z + distance);
				camera.lookAt(center.x, frameCenterY, center.z);

				onLoaded();
			},
			undefined,
			(error) => {
				if (!cancelled) {
					console.error('VRM load error:', error);
					onError();
				}
			}
		);

		return () => {
			cancelled = true;
			if (vrmRef.current) {
				scene.remove(vrmRef.current.scene);
				VRMUtils.deepDispose(vrmRef.current.scene);
				vrmRef.current = null;
			}
		};
	}, [vrmUrl]);

	// Picks up a default-pose animation that starts existing, or changes,
	// after the VRM has already loaded (the initial-load case is handled
	// directly in the VRM-load effect above). No-ops if the VRM isn't ready
	// yet — that case is instead covered by the value already being current
	// in defaultPoseAnimationUrlRef by the time the VRM-load effect reads it.
	useEffect(() => {
		const vrm = vrmRef.current;
		if (!vrm) return;

		if (defaultPoseAnimationUrl) {
			void startDefaultLoop(vrm, defaultPoseAnimationUrl);
			return;
		}

		// The default animation was removed (e.g. deleted mid-session). If
		// it's currently the active idle animation, stop it and blend back
		// to the static rest pose instead of leaving it looping on stale
		// data — but leave a pose that's currently mid-trigger alone; its
		// own 'finished' handler already re-checks this value when it
		// completes and will fall back to the rest pose correctly then.
		loadedDefaultUrlRef.current = null;
		if (defaultActionRef.current) {
			if (defaultLoopActiveRef.current) {
				poseBlendFromRef.current = captureBoneQuaternions(vrm);
				poseBlendRef.current = { active: true, mode: 'out', elapsed: 0 };
			}
			defaultActionRef.current.stop();
			defaultActionRef.current = null;
			defaultLoopActiveRef.current = false;
		}
	}, [defaultPoseAnimationUrl, startDefaultLoop]);

	// Plays a triggered pose's uploaded body animation once, then returns to
	// idle. Loading is async (file fetch + parse) so it stays in an effect
	// with a locally-scoped closure rather than the render-time-derivation
	// pattern used elsewhere in this file.
	useEffect(() => {
		if (!poseTriggerId || !poseAnimationUrl || !vrmRef.current) return;
		if (loadedPoseTriggerRef.current === poseTriggerId) return;
		loadedPoseTriggerRef.current = poseTriggerId;

		// Set synchronously (not after the async load below resolves) so
		// sway/expression-hold gating reacts to the trigger immediately
		// instead of lagging by however long the file takes to fetch — and
		// cancel any hold left over from a still-settling previous pose,
		// since this new trigger supersedes it.
		posePlayingRef.current = true;
		poseHoldRemainingRef.current = 0;
		pendingPoseTransitionRef.current = null;

		let cancelled = false;

		const loadAndPlay = async () => {
			const vrm = vrmRef.current;
			if (!vrm) return;

			let clip = null;
			try {
				clip = await loadPoseClip(poseAnimationUrl, vrm);
			} catch (error) {
				console.error('[VrmAvatar] pose animation load error:', error);
			}

			if (cancelled || !clip) {
				if (!cancelled) posePlayingRef.current = false;
				return;
			}

			const mixer = ensureMixer(vrm);

			// A triggered pose takes priority over the default loop — stop it
			// so it isn't fighting the trigger for the same bones; its
			// mixer 'finished' handler (in ensureMixer) resumes it once this
			// pose finishes, if it's still configured then.
			defaultActionRef.current?.stop();
			defaultLoopActiveRef.current = false;

			poseActionRef.current?.stop();

			// Blend in from the current (idle, or default-loop) bone pose
			// into the new clip's motion over the next few frames, instead
			// of snapping straight to frame 0 of the clip.
			poseBlendFromRef.current = captureBoneQuaternions(vrm);
			poseBlendRef.current = { active: true, mode: 'in', elapsed: 0, resumingDefault: false };

			poseClipDurationRef.current = clip.duration;
			const action = mixer.clipAction(clip);
			action.setLoop(LoopOnce);
			action.clampWhenFinished = true;
			action.reset().play();
			poseActionRef.current = action;
			posePlayingRef.current = true;
		};

		void loadAndPlay();

		return () => {
			cancelled = true;
		};
	}, [poseTriggerId, ensureMixer]);

	useFrame((_, delta) => {
		if (!vrmRef.current) return;
		const vrm = vrmRef.current;

		// Hold the expression briefly, then decay back to neutral so she
		// doesn't stay frozen in the last emotion forever.
		if (emotion !== lastEmotionRef.current) {
			lastEmotionRef.current = emotion;
			expressionHoldRef.current = 0;
		} else {
			expressionHoldRef.current += delta;
		}
		const expressionActive = expressionHoldRef.current < EXPRESSION_HOLD_SECONDS;

		// A triggered pose's facial blendshapes hold-then-decay on their own
		// timer, exactly like emotion's — a pose is a self-contained
		// trigger/perform/return-to-normal event, not something that waits
		// for a later chat message to clear. Re-signaling the same pose
		// (poseTriggerId changes even if the pose name repeats) restarts it.
		if (poseTriggerId !== lastPoseTriggerRef.current) {
			lastPoseTriggerRef.current = poseTriggerId;
			poseExpressionHoldRef.current = 0;
		} else {
			poseExpressionHoldRef.current += delta;
		}
		// When the triggered pose has a body animation, its facial expression
		// rides along with the body's actual playback instead of a fixed
		// timer — otherwise a long animation outlasts the timer and the face
		// goes blank while the body keeps moving. A pose with blendshapes but
		// no animation file has no body state to sync to, so it keeps the
		// fixed-hold behavior.
		const poseExpressionActive = poseAnimationUrl ? posePlayingRef.current : poseExpressionHoldRef.current < EXPRESSION_HOLD_SECONDS;

		// Lerp expression blendshapes toward targets (~300ms to converge).
		// Expression names come entirely from the assistant's own mapping —
		// also lerp any previously-active expression down to 0 even if it's
		// no longer targeted, so switching emotions/poses doesn't leave one stuck.
		// The default pose's blendshapes are a persistent baseline, not a
		// decaying trigger — they apply whenever no triggered pose is
		// currently in its hold window (never triggered, or expired), falling
		// back to genuinely empty (pure neutral) when no default pose is
		// configured, since getPoseBlendshapes('default') then returns [].
		const emotionTargets = expressionActive ? blendshapes : [];
		const poseTargets = poseExpressionActive && poseBlendshapes.length > 0 ? poseBlendshapes : defaultPoseBlendshapes;
		const targets = [...emotionTargets, ...poseTargets];
		const targetMap = Object.fromEntries(targets.filter((t) => t.expression !== 'blink').map((t) => [t.expression, t.weight]));
		const activeExpressions = new Set([...Object.keys(currentWeightsRef.current), ...Object.keys(targetMap)]);
		activeExpressions.delete('blink');
		for (const expr of activeExpressions) {
			const target = targetMap[expr] ?? 0;
			const current = currentWeightsRef.current[expr] ?? 0;
			const lerped = current + (target - current) * Math.min(delta / 0.3, 1);
			currentWeightsRef.current[expr] = lerped;
			vrm.expressionManager.setValue(expr, lerped);
		}

		// Idle blink: close over 80ms, open over 70ms, wait 2–6s
		const blink = blinkRef.current;
		blink.phaseElapsed += delta;
		if (blink.phase === 'waiting' && blink.phaseElapsed >= blink.threshold) {
			blink.phase = 'closing';
			blink.phaseElapsed = 0;
		} else if (blink.phase === 'closing') {
			const t = Math.min(blink.phaseElapsed / 0.08, 1);
			vrm.expressionManager.setValue('blink', t);
			if (t >= 1) {
				blink.phase = 'opening';
				blink.phaseElapsed = 0;
			}
		} else if (blink.phase === 'opening') {
			const t = Math.max(1 - blink.phaseElapsed / 0.07, 0);
			vrm.expressionManager.setValue('blink', t);
			if (t <= 0) {
				blink.phase = 'waiting';
				blink.phaseElapsed = 0;
				blink.threshold = 2 + Math.random() * 4;
			}
		}

		// Sinusoidal head sway applied to the humanoid head bone — paused
		// while a pose's body animation is playing so the two don't fight
		// over the same bone. The phase timer only advances while sway is
		// actually being applied, so it resumes at exactly the phase it
		// paused at rather than jumping ahead by however long the pose took
		// (which would otherwise land the sine wave at an unrelated value
		// and snap the head the instant sway resumes).
		const headBone = vrm.humanoid.getNormalizedBoneNode('head');
		if (headBone && !posePlayingRef.current && !defaultLoopActiveRef.current) {
			elapsedRef.current += delta;
			headBone.rotation.y = Math.sin(elapsedRef.current * 0.6) * 0.03;
			headBone.rotation.z = Math.sin(elapsedRef.current * 0.4) * 0.015;
		}

		// Counts down the hold applied to a single-frame "pose" clip (set by
		// the mixer's 'finished' handler) before actually starting the
		// blend back to rest/default — see POSE_STATIC_CLIP_SECONDS.
		if (poseHoldRemainingRef.current > 0) {
			poseHoldRemainingRef.current -= delta;
			if (poseHoldRemainingRef.current <= 0) {
				poseHoldRemainingRef.current = 0;
				const transition = pendingPoseTransitionRef.current;
				pendingPoseTransitionRef.current = null;
				transition?.();
			}
		}

		mixerRef.current?.update(delta);

		// Manual blend between the captured "from" bone pose and either the
		// mixer's live output (blending *in* to a newly triggered clip) or
		// the relaxed rest pose (blending *out* once a clip finishes) — the
		// mixer alone only cross-fades between two of its own actions, and
		// there's no persistent "idle" action to cross-fade against here.
		const blend = poseBlendRef.current;
		if (blend.active && poseBlendFromRef.current) {
			blend.elapsed += delta;
			const t = Math.min(blend.elapsed / POSE_BLEND_SECONDS, 1);

			if (blend.mode === 'in') {
				for (const [name, fromQuat] of poseBlendFromRef.current) {
					const node = vrm.humanoid.getNormalizedBoneNode(name);
					if (!node) continue;
					const live = node.quaternion.clone();
					node.quaternion.slerpQuaternions(fromQuat, live, t);
				}
			} else if (blend.mode === 'out' && restPoseRef.current) {
				for (const [name, fromQuat] of poseBlendFromRef.current) {
					const node = vrm.humanoid.getNormalizedBoneNode(name);
					const restQuat = restPoseRef.current.get(name);
					if (!node || !restQuat) continue;
					node.quaternion.slerpQuaternions(fromQuat, restQuat, t);
				}
			}

			if (t >= 1) {
				blend.active = false;
				if (blend.mode === 'out') {
					if (restPoseRef.current) applyBoneQuaternions(vrm, restPoseRef.current);
					posePlayingRef.current = false;
				} else if (blend.mode === 'in' && blend.resumingDefault) {
					// The triggered pose is now fully handed off to the
					// resumed default loop — clear posePlayingRef so sway can
					// take back over later if the default loop ever stops
					// (e.g. its animation gets removed). defaultLoopActiveRef
					// alone continues gating sway while the loop is active.
					posePlayingRef.current = false;
				}
				blend.mode = null;
			}
		}
		// No manual freeze needed during the hold window itself: the pose's
		// action is deliberately left running (not .stop()'d — see the
		// 'finished' handler), so mixerRef.current?.update(delta) above keeps
		// re-asserting the clip's true clamped final frame on its own every
		// frame for the whole hold, the same way it does while the clip is
		// still actually playing.

		vrm.update(delta);
	});

	return null;
}

function loadTexture(url, { mirrorX = false } = {}) {
	return new Promise((resolve, reject) => {
		new TextureLoader().load(
			url,
			(texture) => {
				texture.colorSpace = SRGBColorSpace;
				if (mirrorX) {
					// Cancels out a horizontal mirror in the backdrop cylinder's
					// mapping (confirmed by signage text rendering backwards) —
					// flips the U coordinate regardless of which specific step
					// in the mapping introduced it.
					texture.wrapS = RepeatWrapping;
					texture.repeat.x = -1;
					texture.offset.x = 1;
				}
				resolve(texture);
			},
			undefined,
			reject
		);
	});
}

// Renders the current floor + curved-backdrop pair, cross-fading in a new
// pair (default -> generated, or generated -> generated) as it finishes
// loading rather than swapping abruptly (FR-018). Two texture "slots" —
// active and incoming — are kept mounted side by side during a transition;
// once the fade completes, incoming becomes active and the old texture is
// disposed.
function AvatarBackgroundScene({ floorUrl, surroundingsUrl }) {
	const [slots, setSlots] = useState({ active: null, incoming: null });
	const appliedUrlsRef = useRef(null);
	const activeOpacityRef = useRef(0);
	const incomingOpacityRef = useRef(0);
	const transitionElapsedRef = useRef(null);
	const activeMaterialsRef = useRef({ floor: null, surroundings: null });
	const incomingMaterialsRef = useRef({ floor: null, surroundings: null });

	useEffect(() => {
		const urlKey = `${floorUrl}|${surroundingsUrl}`;
		if (appliedUrlsRef.current === urlKey) return;
		appliedUrlsRef.current = urlKey;

		let cancelled = false;

		Promise.all([loadTexture(floorUrl), loadTexture(surroundingsUrl, { mirrorX: true })])
			.then(([floorTexture, surroundingsTexture]) => {
				if (cancelled) return;
				setSlots((prev) => ({ active: prev.active, incoming: { floorTexture, surroundingsTexture, key: urlKey } }));
				transitionElapsedRef.current = 0;
			})
			.catch((err) => console.error('[VrmAvatar] background texture load failed', err));

		return () => {
			cancelled = true;
		};
	}, [floorUrl, surroundingsUrl]);

	// Dispose the texture that was active whenever it's replaced or the scene unmounts.
	useEffect(() => {
		const active = slots.active;
		return () => {
			active?.floorTexture.dispose();
			active?.surroundingsTexture.dispose();
		};
	}, [slots.active]);

	useFrame((_, delta) => {
		if (transitionElapsedRef.current !== null) {
			transitionElapsedRef.current += delta;
			const t = Math.min(transitionElapsedRef.current / BACKGROUND_FADE_SECONDS, 1);
			incomingOpacityRef.current = t;
			activeOpacityRef.current = slots.active ? 1 - t : 0;

			if (t >= 1) {
				transitionElapsedRef.current = null;
				activeOpacityRef.current = 1;
				incomingOpacityRef.current = 0;
				setSlots((prev) => ({ active: prev.incoming, incoming: null }));
			}
		} else if (slots.active) {
			activeOpacityRef.current = 1;
		}

		if (activeMaterialsRef.current.floor) activeMaterialsRef.current.floor.opacity = activeOpacityRef.current;
		if (activeMaterialsRef.current.surroundings) activeMaterialsRef.current.surroundings.opacity = activeOpacityRef.current;
		if (incomingMaterialsRef.current.floor) incomingMaterialsRef.current.floor.opacity = incomingOpacityRef.current;
		if (incomingMaterialsRef.current.surroundings) incomingMaterialsRef.current.surroundings.opacity = incomingOpacityRef.current;
	});

	return (
		<>
			{slots.active && (
				<group key={`active-${slots.active.key}`}>
					<mesh rotation={[-Math.PI / 2, 0, 0]}>
						<planeGeometry args={[FLOOR_SIZE, FLOOR_SIZE]} />
						<meshBasicMaterial ref={(m) => (activeMaterialsRef.current.floor = m)} map={slots.active.floorTexture} transparent opacity={0} />
					</mesh>
					<mesh position={[0, BACKDROP_Y, 0]}>
						<cylinderGeometry args={[BACKDROP_RADIUS, BACKDROP_RADIUS, BACKDROP_HEIGHT, 32, 1, true, BACKDROP_THETA_START, BACKDROP_THETA_LENGTH]} />
						<meshBasicMaterial ref={(m) => (activeMaterialsRef.current.surroundings = m)} map={slots.active.surroundingsTexture} transparent opacity={0} side={BackSide} />
					</mesh>
				</group>
			)}
			{slots.incoming && (
				<group key={`incoming-${slots.incoming.key}`}>
					<mesh rotation={[-Math.PI / 2, 0, 0]}>
						<planeGeometry args={[FLOOR_SIZE, FLOOR_SIZE]} />
						<meshBasicMaterial ref={(m) => (incomingMaterialsRef.current.floor = m)} map={slots.incoming.floorTexture} transparent opacity={0} />
					</mesh>
					<mesh position={[0, BACKDROP_Y, 0]}>
						<cylinderGeometry args={[BACKDROP_RADIUS, BACKDROP_RADIUS, BACKDROP_HEIGHT, 32, 1, true, BACKDROP_THETA_START, BACKDROP_THETA_LENGTH]} />
						<meshBasicMaterial ref={(m) => (incomingMaterialsRef.current.surroundings = m)} map={slots.incoming.surroundingsTexture} transparent opacity={0} side={BackSide} />
					</mesh>
				</group>
			)}
		</>
	);
}

export default function VrmAvatar({ vrmUrl, emotion, blendshapes = [], poseBlendshapes = [], poseAnimationUrl = null, poseTriggerId = null, defaultPoseBlendshapes = [], defaultPoseAnimationUrl = null, assistantId = null, conversationId = null }) {
	const [isLoading, setIsLoading] = useState(true);
	const [loadError, setLoadError] = useState(false);
	const [syncedUrl, setSyncedUrl] = useState(vrmUrl);
	const { background } = useAvatarBackground(assistantId, conversationId, !!(assistantId && conversationId));

	if (syncedUrl !== vrmUrl) {
		setSyncedUrl(vrmUrl);
		setIsLoading(true);
		setLoadError(false);
	}

	if (loadError) {
		return (
			<img
				src={veraAvatar}
				alt="avatar"
				className="w-full h-full object-contain"
			/>
		);
	}

	const floorUrl = background?.floor_url || defaultFloor;
	const surroundingsUrl = background?.surroundings_url || defaultSurroundings;

	return (
		<div className="relative w-full h-full">
			{isLoading && (
				<div className="absolute inset-0 flex items-center justify-center z-10">
					<span className="text-fg-3 text-[0.65rem] tracking-[0.1em]">LOADING...</span>
				</div>
			)}
			<Canvas
				camera={{ position: [0, 1.4, 1.2], fov: 30, near: 0.01, far: 20 }}
				gl={{ alpha: true }}
				style={{ background: 'transparent', width: '100%', height: '100%' }}
			>
				<ambientLight intensity={0.6} />
				<directionalLight position={[0, 2, 2]} intensity={1} />
				<AvatarBackgroundScene floorUrl={floorUrl} surroundingsUrl={surroundingsUrl} />
				<VrmScene
					vrmUrl={vrmUrl}
					emotion={emotion}
					blendshapes={blendshapes}
					poseBlendshapes={poseBlendshapes}
					poseAnimationUrl={poseAnimationUrl}
					poseTriggerId={poseTriggerId}
					defaultPoseBlendshapes={defaultPoseBlendshapes}
					defaultPoseAnimationUrl={defaultPoseAnimationUrl}
					onLoaded={() => setIsLoading(false)}
					onError={() => {
						setIsLoading(false);
						setLoadError(true);
					}}
				/>
			</Canvas>
		</div>
	);
}
