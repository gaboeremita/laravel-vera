import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { AudioListener, Ray, Raycaster, Vector2 } from 'three';
import { PLAYER_EYE_HEIGHT } from './collisionCheck.js';
import { environmentBounds, renderFloorMaps } from './floorMaps.js';
import { NAVIGATION_VERSION, createNavigationGrid } from './worldNavigation.js';
import { STORES, floorMapCacheKey, navigationCacheKey, readCached, writeCached } from '../../utils/worldCache.js';
import { floorBounds } from './worldMapProjection.js';
import { freezeClock, resumeClock } from './pauseClock.js';
import FirstPersonController from './FirstPersonController.jsx';
import NameTags from './NameTags.jsx';
import ZoneOverlay from './ZoneOverlay.jsx';
import ArrivalRing from './ArrivalRing.jsx';
import ThoughtBubble from './ThoughtBubble.jsx';
import { OffscreenIndicatorTracker } from './OffscreenIndicator.jsx';
import InteractionSystem from './InteractionSystem.jsx';
import LocationTracker from './LocationTracker.jsx';
import PassageTracker from './PassageTracker.jsx';
import FocusTracker from './FocusTracker.jsx';
import SpotBeacons from './SpotBeacons.jsx';
import ResidentController from './ResidentController.jsx';
import WorldEnvironment from './WorldEnvironment.jsx';
import { blockingObjectIds } from '../../utils/activityResponses.js';
import { homeState } from './residentHome.js';
import PerformanceProbe from './PerformanceProbe.jsx';
import EnvironmentDetail from './EnvironmentDetail.jsx';
import BackdropRenderer from './BackdropRenderer.jsx';
import { DEFAULT_CAMERA_FAR } from './drawDistance.js';
import { CLICK_SLOP, pickResident } from './residentPick.js';
import { placementAlong } from './residentPlacement.js';

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
		};
	}, [camera, listenerRef]);

	return null;
}

function CameraReach({ far }) {
	const get = useThree((state) => state.get);

	useEffect(() => {
		const { camera } = get();
		camera.far = far;
		camera.updateProjectionMatrix();
		return () => {
			camera.far = DEFAULT_CAMERA_FAR;
			camera.updateProjectionMatrix();
		};
	}, [get, far]);

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

const FLOOR_MAP_VERSION = 3;

/** Renders each floor's map once per world file; later visits read the kept images. */
function FloorMapRenderer({ layout, environment, fileUrl, onRendered }) {
	const { gl, scene } = useThree();

	useEffect(() => {
		let cancelled = false;
		let urls = [];
		let idle = null;
		const key = floorMapCacheKey(fileUrl, layout, FLOOR_MAP_VERSION);
		const show = (maps) => {
			const shown = maps.map((map) => ({ ...map, url: URL.createObjectURL(map.blob) }));
			urls = shown.map((map) => map.url);
			if (cancelled) urls.forEach((url) => URL.revokeObjectURL(url));
			else onRendered(shown);
		};
		const render = async () => {
			const maps = await renderFloorMaps({ renderer: gl, scene, layout, environmentRoot: environment.root, detail: environment.detail, fallbackGroundY: environment.spawnPosition.y });
			if (key && maps.length > 0 && !cancelled && !environment.collisionWorld.disposed) void writeCached(STORES.floorMaps, key, maps);
			show(maps);
		};
		void (key ? readCached(STORES.floorMaps, key) : Promise.resolve(null)).then((kept) => {
			if (cancelled) return;
			if (kept) {
				show(kept);
				return;
			}
			idle = window.requestIdleCallback
				? window.requestIdleCallback(() => { void render(); }, { timeout: 8000 })
				: window.setTimeout(() => { void render(); }, 3000);
		});
		return () => {
			cancelled = true;
			if (idle !== null) {
				if (window.cancelIdleCallback) window.cancelIdleCallback(idle);
				else window.clearTimeout(idle);
			}
			urls.forEach((url) => URL.revokeObjectURL(url));
		};
	}, [environment, fileUrl, gl, scene, layout, onRendered]);

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

/**
 * Builds the walkable grid a few milliseconds per frame so loading never
 * stalls, once per world file; later visits read the kept grid.
 */
function NavigationBuilder({ layout, environment, fileUrl, navigationRef }) {
	useEffect(() => {
		const options = navigationOptions(layout, environment);
		if (!options) return undefined;
		const grid = createNavigationGrid(environment.collisionWorld, options);
		const key = navigationCacheKey(fileUrl, options, NAVIGATION_VERSION);
		let frame = null;
		let cancelled = false;
		const step = () => {
			// Collision thrown away mid-build (the world reloading) would leave a grid with no ground.
			if (cancelled || environment.collisionWorld.disposed) return;
			if (grid.build(NAVIGATION_BUILD_BUDGET_MS)) {
				if (environment.collisionWorld.disposed) return;
				navigationRef.current = grid;
				if (key && grid.hasGround) void writeCached(STORES.navigation, key, grid.snapshot());
				return;
			}
			frame = requestAnimationFrame(step);
		};
		void (key ? readCached(STORES.navigation, key) : Promise.resolve(null)).then((kept) => {
			if (cancelled) return;
			if (grid.restore(kept)) {
				navigationRef.current = grid;
				return;
			}
			frame = requestAnimationFrame(step);
		});
		return () => {
			cancelled = true;
			cancelAnimationFrame(frame);
			navigationRef.current = null;
		};
	}, [layout, environment, fileUrl, navigationRef]);

	return null;
}

const NO_PASSAGES = [];
const NO_ARRIVALS = [];
const NO_SHIMMERING = [];

/**
 * Reports a plain click on a resident, with where on screen it landed. A
 * press dragged further than CLICK_SLOP turns the camera and picks nobody.
 */
function ResidentClickPicker({ residentPositions, onResidentClick }) {
	const { camera, gl } = useThree();

	useEffect(() => {
		const canvas = gl.domElement;
		const raycaster = new Raycaster();
		const pointer = new Vector2();
		let pressedAt = null;
		const pointerDown = (event) => {
			pressedAt = event.button === 0 ? { x: event.clientX, y: event.clientY } : null;
		};
		const pointerUp = (event) => {
			if (!pressedAt || event.button !== 0) return;
			const moved = Math.hypot(event.clientX - pressedAt.x, event.clientY - pressedAt.y);
			pressedAt = null;
			if (moved > CLICK_SLOP) return;
			const rect = canvas.getBoundingClientRect();
			pointer.set(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
			raycaster.setFromCamera(pointer, camera);
			const residentId = pickResident(raycaster.ray.origin, raycaster.ray.direction, residentPositions.current);
			if (residentId !== null) onResidentClick(residentId, { x: event.clientX, y: event.clientY });
		};
		canvas.addEventListener('pointerdown', pointerDown);
		canvas.addEventListener('pointerup', pointerUp);
		return () => {
			canvas.removeEventListener('pointerdown', pointerDown);
			canvas.removeEventListener('pointerup', pointerUp);
		};
	}, [camera, gl, residentPositions, onResidentClick]);

	return null;
}

/**
 * Carries a resident under the cursor for the creator. They stay at the last
 * spot they can stand on, a plain click drops them there, and a press dragged
 * further than CLICK_SLOP turns the camera. Unless dropped, they go back to
 * where they were picked up from when this unmounts.
 */
function ResidentPlacer({ residentId, collisionWorld, navigation, residentCommands, onPlace, onCancel }) {
	const { camera, gl } = useThree();
	const carry = useRef(null);
	const pointer = useRef(null);
	const raycaster = useRef(new Raycaster());
	const lastRay = useRef(new Ray());

	useEffect(() => {
		const registry = residentCommands.current;
		const session = { origin: registry.get(residentId).carry(), spot: null, dropped: false };
		carry.current = session;
		return () => {
			const commands = registry.get(residentId);
			if (!session.dropped) commands?.teleport(session.origin.position, session.origin.facing);
			commands?.release();
		};
	}, [residentCommands, residentId]);

	useEffect(() => {
		const canvas = gl.domElement;
		let pressedAt = null;
		const pointerMove = (event) => {
			if (event.buttons !== 0) return;
			const rect = canvas.getBoundingClientRect();
			pointer.current = new Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
		};
		const pointerDown = (event) => {
			pressedAt = event.button === 0 ? { x: event.clientX, y: event.clientY } : null;
		};
		const pointerUp = (event) => {
			if (!pressedAt || event.button !== 0) return;
			const moved = Math.hypot(event.clientX - pressedAt.x, event.clientY - pressedAt.y);
			pressedAt = null;
			if (moved > CLICK_SLOP) return;
			const session = carry.current;
			if (!session?.spot) return;
			session.dropped = true;
			onPlace(session.spot, session.origin);
		};
		const keyDown = (event) => {
			if (event.code !== 'Escape') return;
			event.stopImmediatePropagation();
			onCancel();
		};
		window.addEventListener('pointermove', pointerMove);
		window.addEventListener('keydown', keyDown, true);
		canvas.addEventListener('pointerdown', pointerDown);
		canvas.addEventListener('pointerup', pointerUp);
		return () => {
			window.removeEventListener('pointermove', pointerMove);
			window.removeEventListener('keydown', keyDown, true);
			canvas.removeEventListener('pointerdown', pointerDown);
			canvas.removeEventListener('pointerup', pointerUp);
		};
	}, [gl, onPlace, onCancel]);

	useFrame(() => {
		const session = carry.current;
		if (!session || session.dropped || !pointer.current) return;
		const { ray } = raycaster.current;
		raycaster.current.setFromCamera(pointer.current, camera);
		if (lastRay.current.equals(ray)) return;
		lastRay.current.copy(ray);
		const spot = placementAlong(ray, collisionWorld, navigation.current);
		if (!spot) return;
		session.spot = spot;
		residentCommands.current.get(residentId)?.teleport(spot, session.origin.facing);
	});

	return null;
}

export default function WorldScene({ world, explorationEnabled, onReady, onError, onResidentChange, onInteract, activePose, initialPosition, initialFacing = null, linkedPassageIds, onEnterPassage, onPlayerPositionChange, residentPositions, activeResidentId = null, onEndConversation, residentVoices, playerView, offscreenIndicator, onFloorMaps, navigation, residentCommands, occupiedSpots, residentStates = {}, thoughts = {}, speech = {}, playerState, playerCommands, collisionWorldRef, onMovementChange, onGetUpIntent, onMoveIntent, onLocationChange, focusLabelRef, focusEnabled = true, focusedObjectId = null, nearbyObjectIds = [], onFocusChange, onNearbyChange, watchedObjectId = null, onWatchedOutOfReach, paused = false, onResidentVoice, statsRef = null, residentDetails = null, shimmeringResidentIds = NO_SHIMMERING, fakeShimmeringResidentIds = NO_SHIMMERING, onBlockedByObject, showZones = false, arrivals = NO_ARRIVALS, onArrivalDone, onResidentClick = null, movingResidentId = null, onPlaceResident, onCancelResidentMove, pickedResidentId = null, onPickedOutOfRange }) {
	const [environment, setEnvironment] = useState(null);
	const audioListener = useRef(null);
	const [playerPosition, setPlayerPosition] = useState([0, 1.6, 4]);
	const [interaction, setInteraction] = useState(null);
	const homeStates = useMemo(() => new Map(world.residents.map((resident) => [resident.id, homeState(resident, world.layout)])), [world.residents, world.layout]);
	const handlePositionChange = useCallback((position) => {
		setPlayerPosition(position);
		onPlayerPositionChange?.(position);
	}, [onPlayerPositionChange]);
	const blockingIds = useMemo(() => [...blockingObjectIds(world.activityTerms)], [world.activityTerms]);
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
		<Canvas camera={{ position: [0, 1.6, 4], fov: 70, near: 0.01, far: DEFAULT_CAMERA_FAR }} className="h-full w-full bg-black">
			<color attach="background" args={['#050913']} />
			<ambientLight intensity={0.7} />
			<directionalLight position={[4, 8, 4]} intensity={2} />
			<CameraAudioListener listenerRef={audioListener} />
			<PauseController paused={paused} audioListener={audioListener} />
			<WorldEnvironment url={world.environmentUrl} blockingObjectIds={blockingIds} onReady={handleReady} onError={onError} />
			{environment && (
				<>
					<FirstPersonController collisionWorld={environment.collisionWorld} navigation={navigation} spawnPosition={spawnPosition} spawnYaw={initialFacing === null ? null : initialFacing + Math.PI} enabled={explorationEnabled} onPositionChange={handlePositionChange} playerState={playerState} playerCommands={playerCommands} onMovementChange={onMovementChange} onGetUpIntent={onGetUpIntent} onMoveIntent={onMoveIntent} onBlockedByObject={onBlockedByObject} />
					{world.residents.map((resident) => <ResidentController key={resident.id} resident={resident} layout={world.layout} onVoice={onResidentVoice} savedState={residentStates[resident.id] ?? homeStates.get(resident.id) ?? null} occupiedSpots={occupiedSpots} playerPosition={playerPosition} paused={!explorationEnabled} activePose={activePose} interaction={interaction} collisionWorld={environment.collisionWorld} residentPositions={residentPositions} residentVoices={residentVoices} audioListener={audioListener} inConversation={resident.id === activeResidentId} navigation={navigation} residentCommands={residentCommands} residentDetails={residentDetails} faintShimmer={shimmeringResidentIds.includes(resident.id)} fakeShimmer={fakeShimmeringResidentIds.includes(resident.id)} />)}
					<PlayerViewTracker viewRef={playerView} playerState={playerState} />
					<CameraReach far={environment.detail.cameraFar} />
					{environment.detail.backdropFar !== null && <BackdropRenderer far={environment.detail.backdropFar} />}
					<EnvironmentDetail root={environment.root} detail={environment.detail} layout={world.layout} playerState={playerState} />
					{statsRef && residentDetails && <PerformanceProbe statsRef={statsRef} residentDetails={residentDetails} />}
					<LocationTracker layout={world.layout} playerState={playerState} onLocationChange={onLocationChange} />
					<PassageTracker passages={world.layout?.passages ?? NO_PASSAGES} linkedPassageIds={linkedPassageIds} playerState={playerState} enabled={explorationEnabled} onEnter={onEnterPassage} />
					<FocusTracker layout={world.layout} playerState={playerState} enabled={explorationEnabled && focusEnabled} labelRef={focusLabelRef} onFocusChange={onFocusChange} onNearbyChange={onNearbyChange} watchedObjectId={watchedObjectId} onWatchedOutOfReach={onWatchedOutOfReach} />
					<SpotBeacons layout={world.layout} focusedObjectId={focusedObjectId} nearbyIds={nearbyObjectIds} occupiedSpots={occupiedSpots} collisionWorld={environment.collisionWorld} />
					{showZones && <ZoneOverlay layout={world.layout} />}
					<ArrivalRing arrivals={arrivals} residentPositions={residentPositions} onDone={onArrivalDone} />
					{onResidentClick && <ResidentClickPicker residentPositions={residentPositions} onResidentClick={onResidentClick} />}
					{movingResidentId !== null && <ResidentPlacer residentId={movingResidentId} collisionWorld={environment.collisionWorld} navigation={navigation} residentCommands={residentCommands} onPlace={onPlaceResident} onCancel={onCancelResidentMove} />}
					<NameTags residents={world.residents} residentPositions={residentPositions} activeResidentId={activeResidentId} />
					<ThoughtBubble thoughts={thoughts} residentPositions={residentPositions} />
					<ThoughtBubble thoughts={speech} residentPositions={residentPositions} variant="speech" />
					<OffscreenIndicatorTracker residentPositions={residentPositions} activeResidentId={activeResidentId} indicatorRef={offscreenIndicator} />
					<NavigationBuilder layout={world.layout} environment={environment} fileUrl={world.environmentUrl} navigationRef={navigation} />
					<FloorMapRenderer layout={world.layout} environment={environment} fileUrl={world.environmentUrl} onRendered={onFloorMaps} />
					<InteractionSystem residents={world.residents} residentPositions={residentPositions} playerState={playerState} onResidentChange={onResidentChange} onInteract={(resident) => { setInteraction({ residentId: resident.id, triggerId: crypto.randomUUID() }); onInteract(resident); }} onEndConversation={onEndConversation} activeResidentId={activeResidentId} enabled={explorationEnabled} pickedResidentId={pickedResidentId} onPickedOutOfRange={onPickedOutOfRange} />
				</>
			)}
		</Canvas>
	);
}
