import { useRef, useEffect, useState } from 'react';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { Box3, BackSide, RepeatWrapping, SRGBColorSpace, TextureLoader, Vector3 } from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMLoaderPlugin, VRMUtils } from '@pixiv/three-vrm';
import veraAvatar from '../../images/vera-avatar.png';
import defaultFloor from '../../images/avatar-background-default-floor.png';
import defaultSurroundings from '../../images/avatar-background-default-surroundings.png';
import useAvatarBackground from '../hooks/useAvatarBackground.js';

const EXPRESSION_HOLD_SECONDS = 3.5;
const BACKGROUND_FADE_SECONDS = 0.4;

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

function VrmScene({ vrmUrl, emotion, blendshapes, onLoaded, onError }) {
	const { scene, camera } = useThree();
	const vrmRef = useRef(null);
	const currentWeightsRef = useRef({});
	const blinkRef = useRef({ phase: 'waiting', phaseElapsed: 0, threshold: 3 });
	const elapsedRef = useRef(0);
	const lastEmotionRef = useRef(emotion);
	const expressionHoldRef = useRef(0);

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

	useFrame((_, delta) => {
		if (!vrmRef.current) return;
		const vrm = vrmRef.current;
		elapsedRef.current += delta;

		// Hold the expression briefly, then decay back to neutral so she
		// doesn't stay frozen in the last emotion forever.
		if (emotion !== lastEmotionRef.current) {
			lastEmotionRef.current = emotion;
			expressionHoldRef.current = 0;
		} else {
			expressionHoldRef.current += delta;
		}
		const expressionActive = expressionHoldRef.current < EXPRESSION_HOLD_SECONDS;

		// Lerp expression blendshapes toward targets (~300ms to converge).
		// Expression names come entirely from the assistant's own mapping —
		// also lerp any previously-active expression down to 0 even if it's
		// no longer targeted, so switching emotions doesn't leave it stuck.
		const targets = expressionActive ? blendshapes : [];
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

		// Sinusoidal head sway applied to the humanoid head bone
		const headBone = vrm.humanoid.getNormalizedBoneNode('head');
		if (headBone) {
			headBone.rotation.y = Math.sin(elapsedRef.current * 0.6) * 0.03;
			headBone.rotation.z = Math.sin(elapsedRef.current * 0.4) * 0.015;
		}

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

export default function VrmAvatar({ vrmUrl, emotion, blendshapes = [], assistantId = null, conversationId = null }) {
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
