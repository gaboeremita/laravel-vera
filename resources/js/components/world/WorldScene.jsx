import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { AudioListener } from 'three';
import { PLAYER_EYE_HEIGHT } from './collisionCheck.js';
import { environmentBounds, renderFloorMaps } from './floorMaps.js';
import { createNavigationGrid } from './worldNavigation.js';
import { floorBounds } from './worldMapProjection.js';
import { freezeClock, resumeClock } from './pauseClock.js';
import FirstPersonController from './FirstPersonController.jsx';
import NameTags from './NameTags.jsx';
import ThoughtBubble from './ThoughtBubble.jsx';
import { OffscreenIndicatorTracker } from './OffscreenIndicator.jsx';
import InteractionSystem from './InteractionSystem.jsx';
import LocationTracker from './LocationTracker.jsx';
import FocusTracker from './FocusTracker.jsx';
import SpotBeacons from './SpotBeacons.jsx';
import ResidentController from './ResidentController.jsx';
import WorldEnvironment from './WorldEnvironment.jsx';

/**
 * Pausing freezes the scene clock, which freezes everything the frame loop
 * drives, and suspends positional voices mid-line.
 */
function PauseController({ paused, audioListener }) {
	const clock = useThree((state) => state.clock);
	const wasPaused = useRef(false);

	useLayoutEffect(() => {
		const context = audioListener.current?.context;
		if (paused) {
			wasPaused.current = true;
			freezeClock(clock);
			if (context?.state === 'running') void context.suspend();
			return;
		}
		if (!wasPaused.current) return;
		wasPaused.current = false;
		resumeClock(clock);
		if (context?.state === 'suspended') void context.resume();
	}, [paused, audioListener, clock]);

	return null;
}

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

function PlayerViewTracker({ viewRef, playerState }) {
	const { camera } = useThree();

	useFrame(() => {
		const foot = playerState.current?.footPosition;
		viewRef.current = foot
			? { x: foot.x, y: foot.y, z: foot.z, yaw: camera.rotation.y }
			: { x: camera.position.x, y: camera.position.y - PLAYER_EYE_HEIGHT, z: camera.position.z, yaw: camera.rotation.y };
	});

	return null;
}

function FloorMapRenderer({ layout, environment, onRendered }) {
	const { gl, scene } = useThree();

	useEffect(() => {
		let cancelled = false;
		let urls = [];
		const render = async () => {
			const maps = await renderFloorMaps({ renderer: gl, scene, layout, environmentRoot: environment.root, fallbackGroundY: environment.spawnPosition.y });
			urls = maps.map((map) => map.url);
			if (cancelled) urls.forEach((url) => URL.revokeObjectURL(url));
			else onRendered(maps);
		};
		const idle = window.requestIdleCallback
			? window.requestIdleCallback(() => { void render(); }, { timeout: 8000 })
			: window.setTimeout(() => { void render(); }, 3000);
		return () => {
			cancelled = true;
			if (window.cancelIdleCallback) window.cancelIdleCallback(idle);
			else window.clearTimeout(idle);
			urls.forEach((url) => URL.revokeObjectURL(url));
		};
	}, [environment, gl, scene, layout, onRendered]);

	return null;
}

const NAVIGATION_BUILD_BUDGET_MS = 4;

function navigationOptions(layout, environment) {
	const bounds = floorBounds(layout, null) ?? environmentBounds(environment.root);
	if (!bounds) return null;
	const floors = layout?.floors ?? [];
	const zones = layout?.zones ?? [];
	const ranges = floors.length ? floors : zones;
	return {
		bounds,
		minY: ranges.length ? Math.min(...ranges.map((range) => range.minY)) : environment.spawnPosition.y - 5,
		maxY: ranges.length ? Math.max(...ranges.map((range) => range.maxY)) : environment.spawnPosition.y + 10,
	};
}

/** Builds the walkable grid a few milliseconds per frame so loading never stalls. */
function NavigationBuilder({ layout, environment, navigationRef }) {
	useEffect(() => {
		const options = navigationOptions(layout, environment);
		if (!options) return undefined;
		const grid = createNavigationGrid(environment.collisionWorld, options);
		let frame = null;
		const step = () => {
			if (grid.build(NAVIGATION_BUILD_BUDGET_MS)) {
				navigationRef.current = grid;
				return;
			}
			frame = requestAnimationFrame(step);
		};
		frame = requestAnimationFrame(step);
		return () => {
			cancelAnimationFrame(frame);
			navigationRef.current = null;
		};
	}, [layout, environment, navigationRef]);

	return null;
}

export default function WorldScene({ world, explorationEnabled, onReady, onError, onResidentChange, onInteract, activePose, initialPosition, onPlayerPositionChange, residentPositions, activeResidentId = null, onEndConversation, residentVoices, playerView, offscreenIndicator, onFloorMaps, navigation, residentCommands, occupiedSpots, residentStates = {}, thoughts = {}, speech = {}, playerState, playerCommands, collisionWorldRef, onMovementChange, onGetUpIntent, onMoveIntent, onLocationChange, focusLabelRef, focusEnabled = true, focusedObjectId = null, nearbyObjectIds = [], onFocusChange, onNearbyChange, watchedObjectId = null, onWatchedOutOfReach, paused = false, onResidentVoice }) {
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
		if (collisionWorldRef) collisionWorldRef.current = loadedEnvironment.collisionWorld;
		onReady();
	}, [onReady, collisionWorldRef]);
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
			<PauseController paused={paused} audioListener={audioListener} />
			<WorldEnvironment url={world.environmentUrl} onReady={handleReady} onError={onError} />
			{environment && (
				<>
					<FirstPersonController collisionWorld={environment.collisionWorld} navigation={navigation} spawnPosition={spawnPosition} enabled={explorationEnabled} onPositionChange={handlePositionChange} playerState={playerState} playerCommands={playerCommands} onMovementChange={onMovementChange} onGetUpIntent={onGetUpIntent} onMoveIntent={onMoveIntent} />
					{world.residents.map((resident) => <ResidentController key={resident.id} resident={resident} layout={world.layout} onVoice={onResidentVoice} savedState={residentStates[resident.id] ?? null} occupiedSpots={occupiedSpots} playerPosition={playerPosition} paused={!explorationEnabled} activePose={activePose} interaction={interaction} collisionWorld={environment.collisionWorld} residentPositions={residentPositions} residentVoices={residentVoices} audioListener={audioListener} inConversation={resident.id === activeResidentId} navigation={navigation} residentCommands={residentCommands} />)}
					<PlayerViewTracker viewRef={playerView} playerState={playerState} />
					<LocationTracker layout={world.layout} playerState={playerState} onLocationChange={onLocationChange} />
					<FocusTracker layout={world.layout} playerState={playerState} enabled={explorationEnabled && focusEnabled} labelRef={focusLabelRef} onFocusChange={onFocusChange} onNearbyChange={onNearbyChange} watchedObjectId={watchedObjectId} onWatchedOutOfReach={onWatchedOutOfReach} />
					<SpotBeacons layout={world.layout} focusedObjectId={focusedObjectId} nearbyIds={nearbyObjectIds} occupiedSpots={occupiedSpots} collisionWorld={environment.collisionWorld} />
					<NameTags residents={world.residents} residentPositions={residentPositions} activeResidentId={activeResidentId} />
					<ThoughtBubble thoughts={thoughts} residentPositions={residentPositions} />
					<ThoughtBubble thoughts={speech} residentPositions={residentPositions} variant="speech" />
					<OffscreenIndicatorTracker residentPositions={residentPositions} activeResidentId={activeResidentId} indicatorRef={offscreenIndicator} />
					<NavigationBuilder layout={world.layout} environment={environment} navigationRef={navigation} />
					<FloorMapRenderer layout={world.layout} environment={environment} onRendered={onFloorMaps} />
					<InteractionSystem residents={world.residents} residentPositions={residentPositions} playerState={playerState} onResidentChange={onResidentChange} onInteract={(resident) => { setInteraction({ residentId: resident.id, triggerId: crypto.randomUUID() }); onInteract(resident); }} onEndConversation={onEndConversation} activeResidentId={activeResidentId} enabled={explorationEnabled} />
				</>
			)}
		</Canvas>
	);
}
