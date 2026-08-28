import { useRef, useEffect, useState } from 'react';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMLoaderPlugin, VRMUtils } from '@pixiv/three-vrm';
import veraAvatar from '../../images/vera-avatar.png';
import { getBlendshapeTargets } from '../utils/vrmExpressions.js';

const ALL_EXPRESSIONS = ['happy', 'sad', 'angry', 'relaxed', 'surprised'];

function VrmScene({ vrmUrl, emotion, onLoaded, onError }) {
	const { scene } = useThree();
	const vrmRef = useRef(null);
	const currentWeightsRef = useRef({});
	const blinkRef = useRef({ phase: 'waiting', phaseElapsed: 0, threshold: 3 });
	const elapsedRef = useRef(0);

	useEffect(() => {
		let cancelled = false;
		const loader = new GLTFLoader();
		loader.register((parser) => new VRMLoaderPlugin(parser));

		loader.load(
			vrmUrl,
			(gltf) => {
				if (cancelled) return;
				const vrm = gltf.userData.vrm;
				VRMUtils.rotateVRM0(vrm);
				vrmRef.current = vrm;
				scene.add(vrm.scene);
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

		// Lerp expression blendshapes toward targets (~300ms to converge)
		const targets = getBlendshapeTargets(emotion);
		const targetMap = Object.fromEntries(targets.map((t) => [t.expression, t.weight]));
		for (const expr of ALL_EXPRESSIONS) {
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

export default function VrmAvatar({ vrmUrl, emotion }) {
	const [isLoading, setIsLoading] = useState(true);
	const [loadError, setLoadError] = useState(false);

	if (loadError) {
		return (
			<img
				src={veraAvatar}
				alt="avatar"
				className="w-full h-full object-contain"
			/>
		);
	}

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
				<VrmScene
					vrmUrl={vrmUrl}
					emotion={emotion}
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
