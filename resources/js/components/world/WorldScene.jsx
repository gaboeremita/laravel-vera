import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AudioListener } from 'three';
import { PLAYER_EYE_HEIGHT } from './collisionCheck.js';
import { renderFloorMaps } from './floorMaps.js';
import FirstPersonController from './FirstPersonController.jsx';
import NameTags from './NameTags.jsx';
import { OffscreenIndicatorTracker } from './OffscreenIndicator.jsx';
import InteractionSystem from './InteractionSystem.jsx';
import ResidentController from './ResidentController.jsx';
import WorldEnvironment from './WorldEnvironment.jsx';

function CameraAudioListener({ listenerRef }) {
	const { camera } = useThree();

	useEffect(() => {
		const listener = new AudioListener();
		camera.add(listener);
		listenerRef.current = listener;
		return () => {
			camera.remove(listener);
			listenerRef.current = null;
			void listener.context.close();
		};
	}, [camera, listenerRef]);

	return null;
}

function PlayerViewTracker({ viewRef }) {
	const { camera } = useThree();

	useFrame(() => {
		const view = viewRef.current ?? {};
		view.x = camera.position.x;
		view.y = camera.position.y - PLAYER_EYE_HEIGHT;
		view.z = camera.position.z;
		view.yaw = camera.rotation.y;
		viewRef.current = view;
	});

	return null;
}

function FloorMapRenderer({ layout, environment, onRendered }) {
	const { gl, scene } = useThree();

	useEffect(() => {
		let cancelled = false;
		const frame = requestAnimationFrame(() => {
			if (cancelled) return;
			onRendered(renderFloorMaps({ renderer: gl, scene, layout, environmentRoot: environment.root, fallbackGroundY: environment.spawnPosition.y }));
		});
		return () => {
			cancelled = true;
			cancelAnimationFrame(frame);
		};
	}, [environment, gl, scene, layout, onRendered]);

	return null;
}

export default function WorldScene({ world, explorationEnabled, onReady, onError, onResidentChange, onInteract, activePose, initialPosition, onPlayerPositionChange, residentPositions, activeResidentId = null, onEndConversation, residentVoices, playerView, offscreenIndicator, onFloorMaps }) {
	const [environment, setEnvironment] = useState(null);
	const audioListener = useRef(null);
	const [playerPosition, setPlayerPosition] = useState([0, 1.6, 4]);
	const [interaction, setInteraction] = useState(null);
	const handlePositionChange = useCallback((position) => {
		setPlayerPosition(position);
		onPlayerPositionChange?.(position);
	}, [onPlayerPositionChange]);
	const handleReady = useCallback((loadedEnvironment) => {
		setEnvironment(loadedEnvironment);
		onReady();
	}, [onReady]);
	const spawnPosition = useMemo(() => {
		if (!environment) return null;
		if (!initialPosition) return environment.spawnPosition;
		return environment.collisionWorld.restorePlayerPosition(initialPosition, environment.spawnPosition);
	}, [environment, initialPosition]);

	return (
		<Canvas camera={{ position: [0, 1.6, 4], fov: 70, near: 0.01, far: 100 }} className="h-full w-full bg-black">
			<color attach="background" args={['#050913']} />
			<ambientLight intensity={0.7} />
			<directionalLight position={[4, 8, 4]} intensity={2} />
			<CameraAudioListener listenerRef={audioListener} />
			<WorldEnvironment url={world.environmentUrl} onReady={handleReady} onError={onError} />
			{environment && (
				<>
					<FirstPersonController collisionWorld={environment.collisionWorld} spawnPosition={spawnPosition} enabled={explorationEnabled} onPositionChange={handlePositionChange} />
					{world.residents.map((resident) => <ResidentController key={resident.id} resident={resident} playerPosition={playerPosition} paused={!explorationEnabled} activePose={activePose} interaction={interaction} collisionWorld={environment.collisionWorld} residentPositions={residentPositions} residentVoices={residentVoices} audioListener={audioListener} inConversation={resident.id === activeResidentId} />)}
					<PlayerViewTracker viewRef={playerView} />
					<NameTags residents={world.residents} residentPositions={residentPositions} activeResidentId={activeResidentId} />
					<OffscreenIndicatorTracker residentPositions={residentPositions} activeResidentId={activeResidentId} indicatorRef={offscreenIndicator} />
					<FloorMapRenderer layout={world.layout} environment={environment} onRendered={onFloorMaps} />
					<InteractionSystem residents={world.residents} residentPositions={residentPositions} onResidentChange={onResidentChange} onInteract={(resident) => { setInteraction({ residentId: resident.id, triggerId: crypto.randomUUID() }); onInteract(resident); }} onEndConversation={onEndConversation} activeResidentId={activeResidentId} enabled={explorationEnabled} />
				</>
			)}
		</Canvas>
	);
}
