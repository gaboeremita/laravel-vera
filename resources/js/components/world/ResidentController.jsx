import { useEffect, useMemo, useRef, useState } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { AnimationMixer, LoopOnce, LoopRepeat, PositionalAudio } from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMLoaderPlugin, VRMUtils } from '@pixiv/three-vrm';
import { applyBoneQuaternions, captureBoneQuaternions, loadPoseClip } from '../VrmAvatar.jsx';
import { CHARACTER_RADIUS, LEAVE_WATER_DEPTH, MAX_MOVEMENT_DELTA, SWIM_DEPTH } from './collisionCheck.js';
import { facingAngleForMovement, fadesIdleForPose, headingToward, makeClipInPlace, shouldFaceUser, turnTowardsAngle } from './residentMotion.js';
import { defaultPoseFor, findWorldMotionPose, resolvePose } from './worldMotionPoses.js';
import { inTalkingReach } from './talkingReach.js';
import { STACK_HEIGHT, claimSpot, releaseSpot, stackTier } from './spotOccupancy.js';

const WALK_SPEED = 0.3;
const WALK_ACCELERATION = 0.18;
const WALK_DECELERATION_SECONDS = 1.2;
const MIN_WALK_SECONDS = 4;
const MAX_WALK_SECONDS = 7;
const MIN_IDLE_SECONDS = 2;
const MAX_IDLE_SECONDS = 4;
const TURN_SPEED = Math.PI * 4;
const FACED_USER_TOLERANCE = 0.03;
const LOCOMOTION_BLEND_SECONDS = 0.2;
const PLACEMENT_BLEND_SECONDS = 0.4;
const RESTING_POSTURES = ['sitting', 'lying', 'reclining', 'swimming'];
const SEAT_CLEARANCE = 0.1;
const SWIM_SPEED_FACTOR = 0.6;
const SWIM_HIPS_BELOW_SURFACE = 0.25;
const TREAD_HIPS_BELOW_SURFACE = 0.55;
const FLOAT_RATE = 3;
const EDGE_REACH = 0.6;
const FOLLOW_EDGE_RADIUS = 2.2;
const FOLLOW_EDGE_DEPTH = 2.5;
const ACTIVITY_HOLD_MS = 6000;
const MIN_WANDER_MS = 30000;
const MAX_WANDER_MS = 60000;
const MIN_WANDER_PAUSE_MS = 2000;
const MAX_WANDER_PAUSE_MS = 5000;
const WANDER_RADIUS = 7;
const WANDER_ATTEMPTS = 12;

function insideOutline(outline, x, z) {
	let inside = false;
	for (let i = 0, j = outline.length - 1; i < outline.length; j = i++) {
		const [xi, zi] = outline[i];
		const [xj, zj] = outline[j];
		if ((zi > z) !== (zj > z) && x < ((xj - xi) * (z - zi)) / (zj - zi) + xi) inside = !inside;
	}
	return inside;
}

const between = (min, max) => min + Math.random() * (max - min);

// Slower than the portrait's own POSE_BLEND_SECONDS (0.25s) — a resident
// has no idle animation to blend back into, so a snappy return read as
// jarring here in a way it doesn't for the portrait.
const POSE_RETURN_SECONDS = 0.6;
const POSE_FADE_SECONDS = 0.25;
// Matches VrmAvatar's EXPRESSION_HOLD_SECONDS — how long a blendshapes-only
// pose (no body animation to ride along with) holds before decaying.
const POSE_EXPRESSION_HOLD_SECONDS = 3.5;
const ROUTE_WALK_SPEED = 1.0;
const ROUTE_ACCELERATION = 2.0;
const ROUTE_TURN_SPEED = Math.PI * 2;
const ROUTE_ARRIVAL_SLOWDOWN = 0.8;
const WAYPOINT_REACHED = 0.35;
const FOLLOW_REPLAN_SECONDS = 0.5;
const FOLLOW_STOP_DISTANCE = 1.4;
const FOLLOW_RESUME_DISTANCE = 2.0;
const STUCK_SECONDS = 2;
const STUCK_MIN_PROGRESS = 0.05;
const MAX_REPLANS = 3;
const BACK_OFF_DISTANCE = 0.6;
const EMBEDDED_CHECK_SECONDS = 0.25;
const EMBEDDED_SECONDS = 0.5;
const VOICE_HEIGHT = 1.5;
const VOICE_REF_DISTANCE = 2;
const VOICE_ROLLOFF = 1.2;

export default function ResidentController({ resident, layout = null, onVoice, savedState = null, playerPosition, paused, activePose, interaction, collisionWorld, residentPositions, residentVoices, audioListener, inConversation = false, navigation, residentCommands, occupiedSpots }) {
	const { scene } = useThree();
	const vrm = useRef(null);
	const mixer = useRef(null);
	const lastPoseTriggerRef = useRef(null);
	const restPoseRef = useRef(null);
	const returnBlendRef = useRef({ active: false, elapsed: 0, from: null });
	const posePlayingRef = useRef(false);
	const heldPoseRef = useRef(null);
	const talkActionsRef = useRef(new Map());
	const talkingUntilRef = useRef(0);
	const onVoiceRef = useRef(onVoice);

	useEffect(() => {
		onVoiceRef.current = onVoice;
	}, [onVoice]);
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
	const routeRef = useRef(null);
	const postureRef = useRef('standing');
	const spotRef = useRef(null);
	const placementRef = useRef(null);
	const embeddedRef = useRef({ since: null, checkAt: 0 });
	const faceUserRequested = useRef(false);
	const faceTargetRef = useRef(null);

	useEffect(() => {
		faceUserRequested.current = inConversation;
	}, [inConversation]);

	useEffect(() => {
		if (inConversation && activePose?.residentId === resident.id) faceUserRequested.current = true;
	}, [activePose, inConversation, resident.id]);
	const postureActionsRef = useRef(new Map());
	const postureHipsHeightRef = useRef(new Map());
	const playPoseRef = useRef(null);
	const poseTokenRef = useRef(0);
	const swimActionRef = useRef(null);
	const swimToEdgeActionRef = useRef(null);
	const motionHipsHeightRef = useRef(new Map());
	const floatOffsetRef = useRef(0);
	const swimmingRef = useRef(false);
	const restAtEdgeRef = useRef(false);
	const { x = 0, y = 0, z = 0 } = resident.position ?? {};
	const position = useMemo(() => collisionWorld.findSpawn({ x, y, z }), [collisionWorld, x, y, z]);
	const distance = position ? Math.hypot(playerPosition[0] - position.x, playerPosition[1] - position.y, playerPosition[2] - position.z) : Infinity;
	const walkAnimationUrl = findWorldMotionPose(resident.assistant.poses, 'walk')?.animationUrl ?? null;
	const walkStartAnimationUrl = findWorldMotionPose(resident.assistant.poses, 'walkStart')?.animationUrl ?? null;
	const walkStopAnimationUrl = findWorldMotionPose(resident.assistant.poses, 'walkStop')?.animationUrl ?? null;
	const poses = resident.assistant.poses;
	const defaultAnimationUrl = defaultPoseFor(poses, 'standing')?.animationUrl ?? null;
	const greetingPose = findWorldMotionPose(poses, 'greeting');
	const swimAnimationUrl = findWorldMotionPose(poses, 'swim')?.animationUrl ?? null;
	const swimToEdgeAnimationUrl = findWorldMotionPose(poses, 'swimToEdge')?.animationUrl ?? null;
	const talkAnimationUrl = findWorldMotionPose(poses, 'talk')?.animationUrl ?? null;
	const talkSittingAnimationUrl = findWorldMotionPose(poses, 'talkSitting')?.animationUrl ?? null;
	const postureDefaultUrls = RESTING_POSTURES.map((posture) => {
		const pose = defaultPoseFor(poses, posture);
		return pose?.posture === posture ? pose.animationUrl ?? null : null;
	}).join('|');

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
			const start = savedState?.position ?? position;
			vrm.current.scene.position.set(start.x, start.y, start.z);
			vrm.current.scene.rotation.y = savedState?.rotation?.y ?? resident.rotation?.y ?? 0;
			if (savedState?.spotId && savedState.posture !== 'standing') {
				const spotId = savedState.spotId;
				// She was already there when the session was saved, so the spot
				// takes her back whatever its capacity.
				if (occupiedSpots) claimSpot(occupiedSpots.current, { id: spotId, capacity: Infinity }, resident.id);
				const tier = occupiedSpots ? stackTier(occupiedSpots.current, spotId, resident.id) : 0;
				const savedSpot = (layout?.objects ?? []).flatMap((object) => object.spots).find((spot) => spot.id === spotId);
				postureRef.current = savedState.posture;
				// The saved height only fits the clip she was in when it was
				// saved, so on a spot it is worked out again from the spot once
				// her posture's clip has loaded.
				spotRef.current = {
					spotId,
					activityId: savedState.activityId ?? null,
					approach: savedState.exitPosition ?? null,
					surfaceY: savedSpot ? savedSpot.position.y + SEAT_CLEARANCE : undefined,
					restY: savedSpot ? undefined : start.y - tier * STACK_HEIGHT,
					holdOffset: 0,
					appliedY: start.y,
					onLeave: () => {
						if (occupiedSpots) releaseSpot(occupiedSpots.current, spotId, resident.id);
					},
				};
			}
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
	}, [distance, loaded, position, resident.id, resident.assistant.vrmUrl, resident.rotation?.y, residentPositions, scene, savedState, occupiedSpots, layout]);

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
			talkingUntilRef.current = performance.now() / 1000 + buffer.duration;
			onVoiceRef.current?.(buffer.duration);
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

	useEffect(() => {
		if (!loaded || !residentCommands || !navigation) return undefined;
		let hold = null;
		const completed = { outcome: 'completed', reason: null };
		// A running activity ends when its work does, or as 'interrupted' when
		// a new action settles it first.
		const interruptible = (work) => new Promise((resolve) => {
			const entry = { resolve, timer: null };
			hold = entry;
			work.then((result) => {
				if (hold !== entry) return;
				hold = null;
				resolve(result);
			});
		});
		const performActivity = (poseName) => interruptible((async () => {
			const result = poseName ? await playPoseRef.current?.(poseName) : null;
			if (!result?.played) await new Promise((resolve) => setTimeout(resolve, ACTIVITY_HOLD_MS));
			return completed;
		})());
		const settle = (outcome, reason = null) => {
			if (hold) {
				clearTimeout(hold.timer);
				hold.resolve({ outcome, reason });
				hold = null;
			}
			const route = routeRef.current;
			if (!route) return;
			routeRef.current = null;
			route.resolve({ outcome, reason });
		};
		const planTo = (target, near, towardUser) => {
			const grid = navigation.current;
			if (!grid) return { reason: 'still mapping this place' };
			const from = vrm.current.scene.position;
			const path = towardUser && swimmingRef.current
				? grid.findPathNear(from, target, FOLLOW_EDGE_RADIUS, FOLLOW_EDGE_DEPTH)
				: near ? grid.findPathNear(from, target) : grid.findPath(from, target);
			return path ? { path } : { reason: 'there is no way to get there from here' };
		};
		const placeAt = (target, rotation) => new Promise((resolve) => {
			const scene = vrm.current.scene;
			placementRef.current = { elapsed: 0, fromPosition: scene.position.clone(), toPosition: target, fromRotation: scene.rotation.y, toRotation: rotation, resolve };
		});
		const leaveSpot = async () => {
			const spot = spotRef.current;
			if (!spot) return;
			spotRef.current = null;
			postureRef.current = 'standing';
			heldPoseRef.current = null;
			spot.onLeave?.();
			if (spot.approach) await placeAt(spot.approach, vrm.current.scene.rotation.y);
		};
		const routeTo = (target, { near = false, towardUser = false } = {}) => new Promise((resolve) => {
			const plan = planTo(target, near, towardUser);
			if (!plan.path) {
				resolve({ outcome: 'failed', reason: plan.reason });
				return;
			}
			routeRef.current = { mode: 'goto', target, near, towardUser, waypoints: plan.path, index: Math.min(1, plan.path.length - 1), moving: true, heading: null, replans: 0, progressAt: null, bestDistance: Infinity, resolve };
		});
		const commands = {
			goTo: async (target, { near = false, towardUser = false } = {}) => {
				settle('interrupted', 'a new action replaced it');
				restAtEdgeRef.current = towardUser && swimmingRef.current;
				await leaveSpot();
				return routeTo(target, { near, towardUser });
			},
			follow: async (getTarget) => {
				settle('interrupted', 'a new action replaced it');
				await leaveSpot();
				return new Promise((resolve) => {
					routeRef.current = { mode: 'follow', getTarget, waypoints: [], index: 0, moving: false, heading: null, replanAt: 0, progressAt: null, bestDistance: Infinity, resolve };
				});
			},
			stop: () => {
				settle('interrupted', 'told to stop');
				return Promise.resolve({ outcome: 'completed', reason: null });
			},
			faceToward: (point) => {
				faceTargetRef.current = { x: point.x, z: point.z };
			},
			talk: (seconds) => {
				talkingUntilRef.current = Math.max(talkingUntilRef.current, performance.now() / 1000 + seconds);
			},
			inTalkingReach: (target) => inTalkingReach(vrm.current.scene.position, target, (from, to) => collisionWorld.hasLineOfSight(from, to)),
			// Walks to the nearest spot close to someone that also has a clear
			// view of them, so she goes around what stands between them.
			approach: async (target) => {
				settle('interrupted', 'a new action replaced it');
				await leaveSpot();
				const grid = navigation.current;
				if (!grid) return { outcome: 'failed', reason: 'still mapping this place' };
				const path = grid.findPathWhere(vrm.current.scene.position, (point) => inTalkingReach(point, target, (from, to) => collisionWorld.hasLineOfSight(from, to)));
				if (!path) return { outcome: 'failed', reason: 'there is no way to get close to them' };
				return new Promise((resolve) => {
					routeRef.current = { mode: 'goto', target, near: true, towardUser: false, waypoints: path, index: Math.min(1, path.length - 1), moving: true, heading: null, replans: 0, progressAt: null, bestDistance: Infinity, resolve };
				});
			},
			use: async ({ spotId, activityId, position: spotPosition, approach, facing, posture, poseName, onLeave }) => {
				settle('interrupted', 'a new action replaced it');
				heldPoseRef.current = null;
				if (spotRef.current) spotRef.current.holdOffset = 0;
				if (spotRef.current?.spotId !== spotId) {
					await leaveSpot();
					const arrival = await routeTo(approach, { near: true });
					if (arrival.outcome !== 'completed') return arrival;
					// A spot marks the surface she sits or lies on; her root goes
					// where the posture clip's hips land just above that surface.
					const ground = vrm.current.scene.position.clone();
					const hipsHeight = posture === 'standing' ? undefined : postureHipsHeightRef.current.get(posture);
					const restY = hipsHeight === undefined ? undefined : spotPosition.y + SEAT_CLEARANCE - hipsHeight;
					const tier = restY === undefined || !occupiedSpots ? 0 : stackTier(occupiedSpots.current, spotId, resident.id);
					const placement = restY === undefined ? ground : { x: spotPosition.x, y: restY + tier * STACK_HEIGHT, z: spotPosition.z };
					await placeAt(placement, facingAngleForMovement(Math.sin(facing), Math.cos(facing)) ?? vrm.current.scene.rotation.y);
					spotRef.current = { spotId, activityId, approach: ground, restY, holdOffset: 0, appliedY: placement.y, onLeave };
				}
				postureRef.current = posture;
				spotRef.current.activityId = activityId;
				return performActivity(poseName);
			},
			zone: async ({ activityId, posture, poseName }) => {
				settle('interrupted', 'a new action replaced it');
				heldPoseRef.current = null;
				// A zone activity belongs to no spot, so she steps off any spot
				// she holds even when her posture stays the same.
				if (spotRef.current?.spotId || posture !== postureRef.current) {
					await leaveSpot();
					if (posture !== 'standing') {
						spotRef.current = { spotId: null, activityId, approach: vrm.current.scene.position.clone(), onLeave: null };
						postureRef.current = posture;
					}
				}
				if (spotRef.current) spotRef.current.activityId = activityId;
				return performActivity(poseName);
			},
			swimToEdge: async () => {
				settle('interrupted', 'a new action replaced it');
				if (!swimmingRef.current) return { outcome: 'failed', reason: 'not in the water' };
				const grid = navigation.current;
				if (!grid) return { outcome: 'failed', reason: 'still mapping this place' };
				const deepAtEdge = (point) => {
					const surface = collisionWorld.waterSurfaceAbove(point.x, point.z, point.y);
					return surface !== null && surface - point.y > SWIM_DEPTH && collisionWorld.isBodyBlocked(point, point, CHARACTER_RADIUS + EDGE_REACH);
				};
				const path = grid.findPathWhere(vrm.current.scene.position, deepAtEdge);
				if (!path) return { outcome: 'failed', reason: 'there is no side of the pool within reach' };
				restAtEdgeRef.current = true;
				return new Promise((resolve) => {
					routeRef.current = { mode: 'goto', target: path[path.length - 1], near: false, towardUser: false, waypoints: path, index: Math.min(1, path.length - 1), moving: true, heading: null, replans: 0, progressAt: null, bestDistance: Infinity, resolve };
				});
			},
			// Strolls, or swims, between random reachable spots, pausing at each,
			// inside a zone's outline or around where she is.
			wander: async ({ outline = null, y = null } = {}) => {
				settle('interrupted', 'a new action replaced it');
				await leaveSpot();
				const grid = navigation.current;
				if (!grid) return { outcome: 'failed', reason: 'still mapping this place' };
				const origin = vrm.current.scene.position.clone();
				const inWater = swimmingRef.current;
				const pickSpot = () => {
					for (let attempt = 0; attempt < WANDER_ATTEMPTS; attempt++) {
						let x;
						let z;
						if (outline) {
							const xs = outline.map(([px]) => px);
							const zs = outline.map(([, pz]) => pz);
							x = between(Math.min(...xs), Math.max(...xs));
							z = between(Math.min(...zs), Math.max(...zs));
							if (!insideOutline(outline, x, z)) continue;
						} else {
							const angle = Math.random() * Math.PI * 2;
							const distance = between(1.5, WANDER_RADIUS);
							x = origin.x + Math.cos(angle) * distance;
							z = origin.z + Math.sin(angle) * distance;
						}
						const spot = grid.nearestPoint({ x, y: y ?? origin.y, z });
						if (!spot) continue;
						if (!outline && Math.abs(spot.y - origin.y) > 0.5) continue;
						if (inWater && !outline) {
							const surface = collisionWorld.waterSurfaceAbove(spot.x, spot.z, spot.y);
							if (surface === null || surface - spot.y <= SWIM_DEPTH) continue;
						}
						return spot;
					}
					return null;
				};
				const until = performance.now() + between(MIN_WANDER_MS, MAX_WANDER_MS);
				let legs = 0;
				while (performance.now() < until) {
					const spot = pickSpot();
					if (!spot) break;
					const leg = await routeTo(spot);
					if (leg.outcome === 'interrupted') return leg;
					if (leg.outcome === 'completed') legs++;
					const pause = await interruptible(new Promise((resolve) => setTimeout(() => resolve(completed), between(MIN_WANDER_PAUSE_MS, MAX_WANDER_PAUSE_MS))));
					if (pause.outcome === 'interrupted') return pause;
				}
				return legs > 0 ? completed : { outcome: 'failed', reason: 'found nowhere to wander to from here' };
			},
			hold: (milliseconds) => new Promise((resolve) => {
				settle('interrupted', 'a new action replaced it');
				hold = {
					resolve,
					timer: setTimeout(() => {
						hold = null;
						resolve({ outcome: 'completed', reason: null });
					}, milliseconds),
				};
			}),
			pose: (name) => interruptible((async () => {
				await playPoseRef.current?.(name);
				return completed;
			})()),
			// A pose that goes with what she says: it never gets her up from
			// a seat and leaves whatever she is doing running.
			gesture: (name) => playPoseRef.current?.(name),
			standUp: leaveSpot,
			posture: () => postureRef.current,
			bodyState: () => ({
				posture: postureRef.current,
				spotId: spotRef.current?.spotId ?? null,
				activityId: spotRef.current?.activityId ?? null,
				pose: heldPoseRef.current?.name ?? null,
			}),
			state: () => {
				const scene = vrm.current.scene;
				const spot = spotRef.current;
				const point = (vector) => (vector ? { x: vector.x, y: vector.y, z: vector.z } : null);
				return {
					position: { x: scene.position.x, y: scene.position.y - floatOffsetRef.current, z: scene.position.z },
					rotation: { y: scene.rotation.y },
					spotId: spot?.spotId ?? null,
					activityId: spot?.activityId ?? null,
					posture: postureRef.current,
					exitPosition: point(spot?.approach),
				};
			},
		};
		const registry = residentCommands.current;
		registry.set(resident.id, commands);
		return () => {
			registry.delete(resident.id);
			settle('interrupted', 'the user left');
		};
	}, [loaded, navigation, resident.id, residentCommands, collisionWorld]);

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
		const loadTransition = async (url, actionRef, loop = false, hold = false) => {
			if (!url) return;
			try {
				const loadedClip = await loadPoseClip(url, vrm.current);
				const hipsName = vrm.current?.humanoid.getNormalizedBoneNode('hips')?.name;
				const hipsTrack = loadedClip?.tracks.find((track) => track.name === `${hipsName}.position`);
				if (hipsTrack) motionHipsHeightRef.current.set(actionRef, hipsTrack.values[1]);
				const clip = loadedClip ? makeClipInPlace(loadedClip) : null;
				if (cancelled || !clip || !vrm.current) return;
				const activeMixer = mixer.current ?? new AnimationMixer(vrm.current.scene);
				mixer.current = activeMixer;
				const action = activeMixer.clipAction(clip);
				action.setLoop(loop ? LoopRepeat : LoopOnce, loop ? Infinity : 1);
				action.clampWhenFinished = hold;
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
			loadTransition(swimAnimationUrl, swimActionRef, true),
			loadTransition(swimToEdgeAnimationUrl, swimToEdgeActionRef, false, true),
		]);
		return () => { cancelled = true; };
	}, [defaultAnimationUrl, loaded, walkStartAnimationUrl, walkStopAnimationUrl, swimAnimationUrl, swimToEdgeAnimationUrl]);

	// Standing she talks in place like she walks; seated, the clip keeps its
	// hip translation like the other seated clips.
	useEffect(() => {
		if (!loaded || !vrm.current) return undefined;
		let cancelled = false;
		const actions = talkActionsRef.current;
		void Promise.all([['standing', talkAnimationUrl], ['sitting', talkSittingAnimationUrl]].map(async ([posture, url]) => {
			if (!url) return;
			try {
				const loadedClip = await loadPoseClip(url, vrm.current);
				if (cancelled || !loadedClip || !vrm.current) return;
				const hipsName = vrm.current.humanoid.getNormalizedBoneNode('hips')?.name;
				const hipsTrack = loadedClip.tracks.find((track) => track.name === `${hipsName}.position`);
				const activeMixer = mixer.current ?? new AnimationMixer(vrm.current.scene);
				mixer.current = activeMixer;
				const action = activeMixer.clipAction(posture === 'standing' ? makeClipInPlace(loadedClip) : loadedClip);
				action.setLoop(LoopRepeat, Infinity);
				actions.set(posture, { action, hipsHeight: hipsTrack?.values[1] });
			} catch (error) {
				console.error(`[ResidentController] ${posture} talk animation load error:`, error);
			}
		}));
		return () => {
			cancelled = true;
			for (const { action } of actions.values()) action.stop();
			actions.clear();
		};
	}, [loaded, talkAnimationUrl, talkSittingAnimationUrl]);

	// Resting postures keep their clips' hip translation, which is what
	// lowers her onto a seat or a bed; only standing locomotion is in place.
	useEffect(() => {
		if (!loaded || !vrm.current) return undefined;
		let cancelled = false;
		const actions = postureActionsRef.current;
		const hipsHeights = postureHipsHeightRef.current;
		const urls = postureDefaultUrls.split('|');
		void Promise.all(RESTING_POSTURES.map(async (posture, index) => {
			const url = urls[index];
			if (!url) return;
			try {
				const clip = await loadPoseClip(url, vrm.current);
				if (cancelled || !clip || !vrm.current) return;
				const activeMixer = mixer.current ?? new AnimationMixer(vrm.current.scene);
				mixer.current = activeMixer;
				const action = activeMixer.clipAction(clip);
				action.setLoop(LoopRepeat, Infinity);
				actions.set(posture, action);
				const hipsName = vrm.current.humanoid.getNormalizedBoneNode('hips')?.name;
				const hipsTrack = clip.tracks.find((track) => track.name === `${hipsName}.position`);
				if (hipsTrack) hipsHeights.set(posture, hipsTrack.values[1]);
			} catch (error) {
				console.error(`[ResidentController] ${posture} default pose load error:`, error);
			}
		}));
		return () => {
			cancelled = true;
			for (const action of actions.values()) action.stop();
			actions.clear();
			hipsHeights.clear();
		};
	}, [loaded, postureDefaultUrls]);

	// A resident's pose is a one-shot trigger (e.g. a greeting wave), not an
	// ongoing state — its body animation (if any) plays once and eases back
	// to rest (see the 'finished' handler below and the blend loop in
	// useFrame), while its facial blendshapes (if any) ride along with that
	// same window — see the expression handling in useFrame.
	useEffect(() => {
		if (!loaded) return undefined;
		// Resolves with { played } once the pose has finished, so a step can
		// wait for it before the next one starts.
		const playPose = async (name) => {
			const skipped = { played: false };
			const pose = resolvePose(poses, name, postureRef.current);
			if (!pose || !vrm.current) return skipped;
			const token = ++poseTokenRef.current;

			activeBlendshapesRef.current = pose.vrmBlendshapes ?? [];
			poseExpressionHoldRef.current = 0;
			poseHasAnimationRef.current = !!pose.animationUrl;

			if (!pose.animationUrl) {
				// Blendshapes-only pose — no body clip to key the expression's
				// active window off of, so useFrame falls back to a fixed hold.
				posePlayingRef.current = false;
				await new Promise((resolve) => setTimeout(resolve, POSE_EXPRESSION_HOLD_SECONDS * 1000));
				return { played: true };
			}

			posePlayingRef.current = true;
			walkActionRef.current?.stop();
			const clip = await loadPoseClip(pose.animationUrl, vrm.current);
			if (token !== poseTokenRef.current || !clip || !vrm.current) {
				if (token === poseTokenRef.current) posePlayingRef.current = false;
				return skipped;
			}
			if (!mixer.current) mixer.current = new AnimationMixer(vrm.current.scene);

			// A held pose becomes what she idles in until she moves, gets up or
			// takes another held pose; the frame loop crossfades into it.
			if (pose.hold) {
				posePlayingRef.current = false;
				const action = mixer.current.clipAction(clip);
				action.setLoop(LoopRepeat, Infinity);
				heldPoseRef.current = { name: pose.name, action, posture: postureRef.current, blendshapes: activeBlendshapesRef.current };
				const hipsName = vrm.current.humanoid.getNormalizedBoneNode('hips')?.name;
				const hipsTrack = clip.tracks.find((track) => track.name === `${hipsName}.position`);
				const defaultHips = postureHipsHeightRef.current.get(postureRef.current);
				const spot = spotRef.current;
				if (spot?.restY !== undefined && hipsTrack && defaultHips !== undefined) spot.holdOffset = defaultHips - hipsTrack.values[1];
				return { played: true };
			}

			const action = mixer.current.clipAction(clip);
			action.reset();
			action.setLoop(LoopOnce, 1);
			action.clampWhenFinished = false;
			// Resting, holding a pose or talking, she has an idle clip running;
			// it fades out so the two do not average, and comes back when this
			// ends.
			const idle = locomotionActionRef.current;
			const fadeIdle = fadesIdleForPose({ idle, pose: action });
			if (fadeIdle) idle.fadeOut(POSE_FADE_SECONDS);
			action.play();
			if (fadeIdle) action.fadeIn(POSE_FADE_SECONDS);

			// With no idle animation loop for world residents (unlike the
			// portrait's default-pose system), holding the clip's last frame
			// forever left long poses visibly stuck mid-gesture once they
			// finished — short poses happened to end close enough to a
			// neutral stance that this went unnoticed. Ease back to the
			// captured rest pose over POSE_RETURN_SECONDS instead of
			// snapping to it instantly (see the blend loop in useFrame).
			return new Promise((resolve) => {
				const onFinished = (event) => {
					if (event.action !== action) return;
					mixer.current?.removeEventListener('finished', onFinished);
					posePlayingRef.current = false;
					const resting = locomotionActionRef.current;
					if (fadeIdle && resting) {
						resting.stopFading();
						resting.enabled = true;
						resting.setEffectiveWeight(1);
					}
					if (heldPoseRef.current) activeBlendshapesRef.current = heldPoseRef.current.blendshapes;
					if (restPoseRef.current && vrm.current) {
						returnBlendRef.current = { active: true, elapsed: 0, from: captureBoneQuaternions(vrm.current) };
					}
					resolve({ played: true });
				};
				mixer.current.addEventListener('finished', onFinished);
			});
		};
		playPoseRef.current = playPose;
		return () => { playPoseRef.current = null; };
	}, [loaded, poses]);

	useEffect(() => {
		if (!loaded) return;
		const trigger = activePose?.residentId === resident.id
			? { triggerId: activePose.triggerId, name: activePose.name }
			: interaction?.residentId === resident.id && greetingPose
				? { triggerId: interaction.triggerId, name: greetingPose.name }
				: null;
		if (!trigger || trigger.triggerId === lastPoseTriggerRef.current) return;
		lastPoseTriggerRef.current = trigger.triggerId;
		void playPoseRef.current?.(trigger.name);
	}, [loaded, activePose, greetingPose, interaction, resident.id]);

	useFrame((state, delta) => {
		if (!vrm.current) return;
		const currentPosition = vrm.current.scene.position;
		currentPosition.y -= floatOffsetRef.current;
		const waterSurface = collisionWorld.waterSurfaceAbove(currentPosition.x, currentPosition.z, currentPosition.y);
		const waterDepth = waterSurface === null ? 0 : waterSurface - currentPosition.y;
		const edgeResting = locomotionPhaseRef.current.name === 'edge';
		const swimming = edgeResting || waterDepth > (swimmingRef.current ? LEAVE_WATER_DEPTH : SWIM_DEPTH);
		if (swimming && !swimmingRef.current) {
			postureRef.current = 'swimming';
			spotRef.current = null;
		} else if (!swimming && swimmingRef.current && postureRef.current === 'swimming') {
			postureRef.current = 'standing';
		}
		swimmingRef.current = swimming;
		const nearEdge = () => collisionWorld.isBodyBlocked(currentPosition, currentPosition, CHARACTER_RADIUS + EDGE_REACH);
		const placement = placementRef.current;
		if (placement) {
			placement.elapsed += delta;
			const t = Math.min(placement.elapsed / PLACEMENT_BLEND_SECONDS, 1);
			currentPosition.set(
				placement.fromPosition.x + (placement.toPosition.x - placement.fromPosition.x) * t,
				placement.fromPosition.y + (placement.toPosition.y - placement.fromPosition.y) * t,
				placement.fromPosition.z + (placement.toPosition.z - placement.fromPosition.z) * t,
			);
			vrm.current.scene.rotation.y = placement.fromRotation + Math.atan2(Math.sin(placement.toRotation - placement.fromRotation), Math.cos(placement.toRotation - placement.fromRotation)) * t;
			if (t >= 1) {
				placementRef.current = null;
				placement.resolve();
			}
		}
		// Her height on a spot follows her tier in a shared spot and the hips
		// of the pose she holds there.
		const talking = performance.now() / 1000 < talkingUntilRef.current && !posePlayingRef.current
			? talkActionsRef.current.get(postureRef.current) ?? null
			: null;
		const heldSpot = spotRef.current;
		if (heldSpot?.surfaceY !== undefined && heldSpot.restY === undefined) {
			const hipsHeight = postureHipsHeightRef.current.get(postureRef.current);
			if (hipsHeight !== undefined) {
				const tier = occupiedSpots ? stackTier(occupiedSpots.current, heldSpot.spotId, resident.id) : 0;
				heldSpot.restY = heldSpot.surfaceY - hipsHeight;
				currentPosition.y = heldSpot.restY + tier * STACK_HEIGHT;
				heldSpot.appliedY = currentPosition.y;
			}
		}
		if (heldSpot?.restY !== undefined && !placementRef.current) {
			const tier = occupiedSpots ? stackTier(occupiedSpots.current, heldSpot.spotId, resident.id) : 0;
			const postureHips = postureHipsHeightRef.current.get(postureRef.current);
			const poseOffset = talking?.hipsHeight !== undefined && postureHips !== undefined ? postureHips - talking.hipsHeight : heldSpot.holdOffset;
			const restingY = heldSpot.restY + tier * STACK_HEIGHT + poseOffset;
			if (Math.abs(restingY - heldSpot.appliedY) > 0.001) {
				heldSpot.appliedY = restingY;
				const rotation = vrm.current.scene.rotation.y;
				placementRef.current = { elapsed: 0, fromPosition: currentPosition.clone(), toPosition: { x: currentPosition.x, y: restingY, z: currentPosition.z }, fromRotation: rotation, toRotation: rotation, resolve: () => {} };
			}
		}
		const currentDistance = Math.hypot(playerPosition[0] - currentPosition.x, playerPosition[1] - currentPosition.y, playerPosition[2] - currentPosition.z);
		let didMove = false;
		const elapsed = state.clock.elapsedTime;
		const embedded = embeddedRef.current;
		const restingPosture = ['sitting', 'lying', 'reclining'].includes(postureRef.current);
		if (placementRef.current || swimming || restingPosture) embedded.since = null;
		else if (elapsed >= embedded.checkAt) {
			embedded.checkAt = elapsed + EMBEDDED_CHECK_SECONDS;
			if (!collisionWorld.isBodyBlocked(currentPosition)) embedded.since = null;
			else if (embedded.since === null) embedded.since = elapsed;
			else if (elapsed - embedded.since >= EMBEDDED_SECONDS) {
				embedded.since = null;
				const grid = navigation?.current;
				const free = collisionWorld.freeBodyPosition(currentPosition, grid);
				if (free) {
					currentPosition.set(free.x, free.y, free.z);
					if (spotRef.current) {
						spotRef.current.onLeave?.();
						spotRef.current = null;
					}
					const activeRoute = routeRef.current;
					if (activeRoute?.mode === 'follow') activeRoute.replanAt = 0;
					else if (activeRoute) {
						const path = grid && (activeRoute.near ? grid.findPathNear(currentPosition, activeRoute.target) : grid.findPath(currentPosition, activeRoute.target));
						if (path && path.length > 1) {
							activeRoute.waypoints = path;
							activeRoute.index = 1;
							activeRoute.bestDistance = Infinity;
							activeRoute.progressAt = elapsed;
						} else {
							routeRef.current = null;
							activeRoute.resolve({ outcome: 'failed', reason: 'got stuck in a wall' });
						}
					}
				}
			}
		}
		const route = routeRef.current;
		if (route) {
			if (route.mode === 'follow' && elapsed >= route.replanAt) {
				route.replanAt = elapsed + FOLLOW_REPLAN_SECONDS;
				const target = route.getTarget();
				if (target) {
					const gap = Math.hypot(target.x - currentPosition.x, target.z - currentPosition.z);
					if (gap <= FOLLOW_STOP_DISTANCE) route.moving = false;
					else if (route.moving || gap >= FOLLOW_RESUME_DISTANCE) {
						const grid = navigation?.current;
						const path = swimming ? grid?.findPathNear(currentPosition, target, FOLLOW_EDGE_RADIUS, FOLLOW_EDGE_DEPTH) : grid?.findPath(currentPosition, target);
						if (path && path.length > 1) {
							route.waypoints = path;
							route.index = 1;
							route.moving = true;
							route.bestDistance = Infinity;
							route.progressAt = elapsed;
						}
					}
				}
			}
			if (route.moving) {
				let waypoint = route.waypoints[route.index];
				while (waypoint && Math.hypot(waypoint.x - currentPosition.x, waypoint.z - currentPosition.z) < WAYPOINT_REACHED) {
					route.index++;
					route.bestDistance = Infinity;
					route.progressAt = elapsed;
					waypoint = route.waypoints[route.index];
				}
				if (!waypoint) {
					route.moving = false;
					if (route.mode === 'goto') {
						routeRef.current = null;
						route.resolve({ outcome: 'completed', reason: null });
					}
				} else {
					const toWaypoint = Math.hypot(waypoint.x - currentPosition.x, waypoint.z - currentPosition.z);
					route.heading = facingAngleForMovement(waypoint.x - currentPosition.x, waypoint.z - currentPosition.z);
					if (route.progressAt === null || toWaypoint < route.bestDistance - STUCK_MIN_PROGRESS) {
						route.bestDistance = toWaypoint;
						route.progressAt = elapsed;
					}
					if (locomotionPhaseRef.current.name === 'walking' && elapsed - route.progressAt > STUCK_SECONDS) {
						// First she steps back and lines up with the opening again;
						// if she sticks at the same place, that spot is ruled out
						// and she looks for another way.
						const grid = navigation?.current;
						const plan = (from) => (route.towardUser && swimming
							? grid.findPathNear(from, route.target, FOLLOW_EDGE_RADIUS, FOLLOW_EDGE_DEPTH)
							: route.near ? grid.findPathNear(from, route.target) : grid.findPath(from, route.target));
						let path = null;
						if (route.mode === 'goto' && grid && route.replans < MAX_REPLANS) {
							if (route.replans === 0) {
								const facing = vrm.current.scene.rotation.y;
								const backOff = grid.nearestPoint({ x: currentPosition.x + Math.sin(facing) * BACK_OFF_DISTANCE, y: currentPosition.y, z: currentPosition.z + Math.cos(facing) * BACK_OFF_DISTANCE });
								const onward = backOff ? plan(backOff) : null;
								if (onward) path = [{ x: currentPosition.x, y: currentPosition.y, z: currentPosition.z }, backOff, ...onward.slice(1)];
							}
							if (!path) {
								grid.blockStep(currentPosition, waypoint);
								path = plan(currentPosition);
							}
						}
						if (path && path.length > 1) {
							route.waypoints = path;
							route.index = 1;
							route.replans++;
							route.bestDistance = Infinity;
							route.progressAt = elapsed;
						} else if (route.mode === 'goto') {
							routeRef.current = null;
							route.resolve({ outcome: 'failed', reason: 'got stuck on the way' });
						} else {
							route.moving = false;
						}
					}
				}
			}
		}
		const routeMoving = Boolean(routeRef.current?.moving);
		const wantsToRoam = !routeRef.current && !spotRef.current && !placementRef.current && resident.behavior === 'roam' && !paused && !inConversation && currentDistance < 30;
		const idleAction = () => talking?.action
			?? (heldPoseRef.current?.posture === postureRef.current ? heldPoseRef.current.action : null)
			?? (postureRef.current === 'standing' ? null : postureActionsRef.current.get(postureRef.current))
			?? defaultActionRef.current;
		const wantsToMove = routeMoving || wantsToRoam;
		if (wantsToMove) heldPoseRef.current = null;
		const locomotion = locomotionPhaseRef.current;
		const moveAction = () => (swimming ? swimActionRef.current ?? walkActionRef.current : walkActionRef.current);
		const noTransition = { current: null };
		const activateLocomotionAction = (action, blendSeconds = LOCOMOTION_BLEND_SECONDS) => {
			if (!action || locomotionActionRef.current === action) return;
			const previousAction = locomotionActionRef.current;
			action.reset().play();
			if (previousAction) action.crossFadeFrom(previousAction, blendSeconds, false);
			locomotionActionRef.current = action;
		};
		const playTransition = (actionRef, name) => {
			const action = actionRef.current;
			if (!action) {
				if (name === 'stopping') beginIdle();
				else locomotion.name = 'walking';
				return;
			}
			activateLocomotionAction(action);
			locomotion.name = name;
			locomotion.endsAt = state.clock.elapsedTime + action.getClip().duration;
		};

		// At the side of the pool she swims up to the edge and rests there
		// until she moves again.
		const beginIdle = () => {
			locomotion.velocity = 0;
			if (swimming && !wantsToMove && swimToEdgeActionRef.current && (restAtEdgeRef.current || nearEdge())) {
				restAtEdgeRef.current = false;
				locomotion.name = 'edge';
				activateLocomotionAction(swimToEdgeActionRef.current);
				return;
			}
			locomotion.name = 'idle';
			locomotion.endsAt = state.clock.elapsedTime + MIN_IDLE_SECONDS + Math.random() * (MAX_IDLE_SECONDS - MIN_IDLE_SECONDS);
			activateLocomotionAction(idleAction());
		};
		if (locomotion.name === 'edge') {
			if (wantsToMove) {
				locomotion.name = 'idle';
				locomotion.endsAt = 0;
			} else if (locomotionActionRef.current !== swimToEdgeActionRef.current) {
				activateLocomotionAction(swimToEdgeActionRef.current);
			} else {
				const edgeAction = swimToEdgeActionRef.current;
				const lastFrame = edgeAction.getClip().duration;
				if (edgeAction.time >= lastFrame - 0.001 || !edgeAction.isRunning()) {
					edgeAction.enabled = true;
					edgeAction.paused = true;
					edgeAction.time = lastFrame;
					edgeAction.setEffectiveWeight(1);
				}
			}
		}
		if (!wantsToMove && ['walking', 'starting', 'turning'].includes(locomotion.name)) {
			playTransition(swimming ? noTransition : walkStopActionRef, 'stopping');
			locomotion.velocity = 0;
		}
		if (locomotion.name === 'stopping' && state.clock.elapsedTime >= locomotion.endsAt) beginIdle();
		if (locomotion.name === 'idle') {
			if (locomotion.endsAt === 0) beginIdle();
			if (routeMoving && routeRef.current.heading !== null) {
				locomotion.name = 'turning';
				locomotion.heading = routeRef.current.heading;
			} else if (wantsToRoam && state.clock.elapsedTime >= locomotion.endsAt) {
				locomotion.name = 'turning';
				locomotion.heading = vrm.current.scene.rotation.y + (Math.random() - 0.5) * 1.4;
			}
		}
		if (locomotion.name === 'idle' && locomotion.endsAt !== 0 && !posePlayingRef.current) activateLocomotionAction(idleAction(), PLACEMENT_BLEND_SECONDS);
		const restingOnSpot = Boolean(spotRef.current) && ['sitting', 'lying', 'reclining'].includes(postureRef.current);
		if (locomotion.name === 'idle' && shouldFaceUser({ requested: faceUserRequested.current, inConversation, routing: Boolean(routeRef.current?.moving), placing: Boolean(placementRef.current), restingOnSpot })) {
			const towardUser = headingToward(currentPosition, { x: playerPosition[0], z: playerPosition[2] });
			if (towardUser === null) faceUserRequested.current = false;
			else {
				vrm.current.scene.rotation.y = turnTowardsAngle(vrm.current.scene.rotation.y, towardUser, TURN_SPEED * Math.min(delta, MAX_MOVEMENT_DELTA));
				const remaining = Math.atan2(Math.sin(towardUser - vrm.current.scene.rotation.y), Math.cos(towardUser - vrm.current.scene.rotation.y));
				if (Math.abs(remaining) < FACED_USER_TOLERANCE) faceUserRequested.current = false;
			}
		}
		const faceTarget = faceTargetRef.current;
		if (faceTarget && locomotion.name === 'idle' && !routeRef.current?.moving && !placementRef.current && !restingOnSpot) {
			const toward = headingToward(currentPosition, faceTarget);
			if (toward === null) faceTargetRef.current = null;
			else {
				vrm.current.scene.rotation.y = turnTowardsAngle(vrm.current.scene.rotation.y, toward, TURN_SPEED * Math.min(delta, MAX_MOVEMENT_DELTA));
				const remaining = Math.atan2(Math.sin(toward - vrm.current.scene.rotation.y), Math.cos(toward - vrm.current.scene.rotation.y));
				if (Math.abs(remaining) < FACED_USER_TOLERANCE) faceTargetRef.current = null;
			}
		}
		if (locomotion.name === 'turning' && routeMoving && routeRef.current.heading !== null) locomotion.heading = routeRef.current.heading;
		if (locomotion.name === 'turning') {
			vrm.current.scene.rotation.y = turnTowardsAngle(vrm.current.scene.rotation.y, locomotion.heading, TURN_SPEED * Math.min(delta, MAX_MOVEMENT_DELTA));
			const headingError = locomotion.heading - vrm.current.scene.rotation.y;
			if (Math.abs(Math.sin(headingError)) < 0.05 && Math.cos(headingError) > 0) playTransition(swimming ? noTransition : walkStartActionRef, 'starting');
		}
		if (locomotion.name === 'starting' && state.clock.elapsedTime >= locomotion.endsAt) {
			locomotion.name = 'walking';
			locomotion.velocity = 0;
			locomotion.endsAt = state.clock.elapsedTime + MIN_WALK_SECONDS + Math.random() * (MAX_WALK_SECONDS - MIN_WALK_SECONDS);
			activateLocomotionAction(moveAction());
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
			if (!didMove || remaining <= 0) playTransition(swimming ? noTransition : walkStopActionRef, 'stopping');
		}
		if (routeMoving && locomotion.name === 'walking') {
			const activeRoute = routeRef.current;
			const step = Math.min(delta, MAX_MOVEMENT_DELTA);
			if (activeRoute.heading !== null) vrm.current.scene.rotation.y = turnTowardsAngle(vrm.current.scene.rotation.y, activeRoute.heading, ROUTE_TURN_SPEED * step);
			const finalWaypoint = activeRoute.waypoints[activeRoute.waypoints.length - 1];
			const toEnd = activeRoute.mode === 'goto' ? Math.hypot(finalWaypoint.x - currentPosition.x, finalWaypoint.z - currentPosition.z) : Infinity;
			// She turns toward the next point before walking, so she does not
			// swing wide into door frames at corners.
			const headingError = activeRoute.heading === null ? 0 : activeRoute.heading - vrm.current.scene.rotation.y;
			const alignment = Math.max(0, Math.cos(headingError)) ** 2;
			const targetVelocity = ROUTE_WALK_SPEED * (swimming ? SWIM_SPEED_FACTOR : 1) * alignment * Math.min(1, Math.max(0.3, toEnd / ROUTE_ARRIVAL_SLOWDOWN));
			locomotion.velocity += Math.sign(targetVelocity - locomotion.velocity) * Math.min(Math.abs(targetVelocity - locomotion.velocity), ROUTE_ACCELERATION * step);
			const distance = locomotion.velocity * step;
			const previousX = currentPosition.x;
			const previousZ = currentPosition.z;
			collisionWorld.move(currentPosition, -Math.sin(vrm.current.scene.rotation.y) * distance, -Math.cos(vrm.current.scene.rotation.y) * distance);
			didMove = Math.hypot(currentPosition.x - previousX, currentPosition.z - previousZ) > 0.0001;
		}
		walkingRef.current = didMove;
		if (didMove && !posePlayingRef.current && !returnBlendRef.current.active) {
			activateLocomotionAction(moveAction());
		}

		// In deep water she floats: her root rises so the swimming clip's hips
		// sit just under the surface, whatever the pool's depth.
		let floatTarget = 0;
		if (swimming) {
			const moving = locomotionActionRef.current === swimActionRef.current && swimActionRef.current !== null;
			const edgeResting = locomotion.name === 'edge';
			const hipsHeight = moving
				? motionHipsHeightRef.current.get(swimActionRef)
				: edgeResting ? motionHipsHeightRef.current.get(swimToEdgeActionRef) : postureHipsHeightRef.current.get('swimming');
			if (hipsHeight !== undefined) {
				const hipsTarget = waterSurface - (moving ? SWIM_HIPS_BELOW_SURFACE : TREAD_HIPS_BELOW_SURFACE);
				floatTarget = Math.max(0, hipsTarget - hipsHeight - currentPosition.y);
			}
		}
		floatOffsetRef.current += (floatTarget - floatOffsetRef.current) * Math.min(1, delta * FLOAT_RATE);
		currentPosition.y += floatOffsetRef.current;

		if (currentDistance < 30) {
			mixer.current?.update(delta);

			// In a resting posture the return blend eases into her posture's
			// default clip as the mixer poses it this frame, since blending to
			// the standing rest pose would pull her half up off the seat.
			const blend = returnBlendRef.current;
			const heldAction = heldPoseRef.current?.posture === postureRef.current ? heldPoseRef.current.action : null;
			const restingAction = heldAction ?? (postureRef.current === 'standing' ? null : postureActionsRef.current.get(postureRef.current));
			if (blend.active && blend.from && restPoseRef.current) {
				blend.elapsed += delta;
				const t = Math.min(blend.elapsed / POSE_RETURN_SECONDS, 1);
				for (const [name, fromQuat] of blend.from) {
					const node = vrm.current.humanoid.getNormalizedBoneNode(name);
					const targetQuat = restingAction ? node?.quaternion.clone() : restPoseRef.current.get(name);
					if (!node || !targetQuat) continue;
					node.quaternion.slerpQuaternions(fromQuat, targetQuat, t);
				}
				if (t >= 1) {
					blend.active = false;
					if (!restingAction) applyBoneQuaternions(vrm.current, restPoseRef.current);
				}
			}

			// A pose's facial expression rides along with its body animation
			// while that's playing; a blendshapes-only pose instead holds for
			// a fixed duration — same rule VrmAvatar applies to the portrait.
			if (!posePlayingRef.current) poseExpressionHoldRef.current += delta;
			const expressionActive = heldPoseRef.current
				|| (poseHasAnimationRef.current ? posePlayingRef.current : poseExpressionHoldRef.current < POSE_EXPRESSION_HOLD_SECONDS);
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
