import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useOutletContext, useParams, useSearchParams } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import WorldScene from '../components/world/WorldScene.jsx';
import WorldChat from '../components/world/WorldChat.jsx';
import WorldTrackPlayer from '../components/world/WorldTrackPlayer.jsx';
import { PLAYER_EYE_HEIGHT } from '../components/world/collisionCheck.js';
import { conversationRangeState } from '../components/world/conversationRange.js';
import { isTypingTarget } from '../components/world/keyboardFocus.js';
import OffscreenIndicator from '../components/world/OffscreenIndicator.jsx';
import WorldMap from '../components/world/WorldMap.jsx';

export default function WorldPage() {
	const { worldId } = useParams();
	const [searchParams] = useSearchParams();
	const sessionId = searchParams.get('session');
	const navigate = useNavigate();
	const { addToast, setHidePortrait } = useOutletContext();
	const [world, setWorld] = useState(null);
	const [session, setSession] = useState(null);
	const [status, setStatus] = useState('loading');
	const [nearbyResident, setNearbyResident] = useState(null);
	const [chatResident, setChatResident] = useState(null);
	const [activePose, setActivePose] = useState(null);
	const latestPosition = useRef(null);
	const residentPositions = useRef(new Map());
	const residentVoices = useRef(new Map());
	const playerView = useRef(null);
	const offscreenIndicator = useRef(null);
	const [floorMaps, setFloorMaps] = useState([]);
	const [mapExpanded, setMapExpanded] = useState(false);
	const [conversationRange, setConversationRange] = useState('ok');

	useEffect(() => {
		setHidePortrait(true);
		return () => setHidePortrait(false);
	}, [setHidePortrait]);

	useEffect(() => {
		const load = async () => {
			try {
				const response = await api.get(route('worlds.show', { world: worldId }));
				if (!response.ok) throw new Error('World unavailable');
				const data = await response.json();
				if (!data.environmentUrl) throw new Error('This world has no environment asset.');
				let selectedSession = null;

				if (sessionId) {
					const sessionsResponse = await api.get(route('worlds.sessions.index', { world: worldId }));
					if (!sessionsResponse.ok) throw new Error('World session unavailable');
					const sessions = await sessionsResponse.json();
					selectedSession = sessions.find((item) => String(item.id) === String(sessionId)) ?? null;
					if (!selectedSession) throw new Error('World session unavailable');
				}

				setSession(selectedSession);
				setWorld(data);
				setStatus('entering');
			} catch (error) { addToast(error.message || 'Failed to load world', 'error'); setStatus('error'); }
		};
		void load();
	}, [addToast, worldId, sessionId]);

	const persistPosition = useCallback(async () => {
		if (!sessionId || !latestPosition.current) return;
		const [x, y, z] = latestPosition.current;
		const response = await api.put(route('worlds.sessions.position.update', { world: worldId, session: sessionId }), { position: { x, y, z } });
		if (response.status === 404) navigate(`/worlds/${worldId}/sessions`);
	}, [worldId, sessionId, navigate]);

	useEffect(() => {
		latestPosition.current = null;
	}, [sessionId]);

	useEffect(() => {
		if (!sessionId) return undefined;
		const interval = setInterval(persistPosition, 10000);
		return () => {
			clearInterval(interval);
			void persistPosition();
		};
	}, [sessionId, persistPosition]);

	const exit = useCallback(() => {
		persistPosition();
		navigate(`/worlds/${worldId}/sessions`);
	}, [navigate, persistPosition, worldId]);
	const openChat = useCallback((resident) => setChatResident(resident), []);
	const closeChat = useCallback(() => setChatResident(null), []);
	const handlePlayerPositionChange = useCallback((position) => { latestPosition.current = position; }, []);
	const getPositions = useCallback(() => {
		const residents = {};
		for (const [residentId, position] of residentPositions.current) residents[residentId] = { x: position.x, y: position.y, z: position.z };
		const eye = latestPosition.current;
		return eye ? { user: { x: eye[0], y: eye[1] - PLAYER_EYE_HEIGHT, z: eye[2] }, residents } : { residents };
	}, []);
	const handleWorldReady = useCallback(() => setStatus('ready'), []);
	const handleWorldError = useCallback((error) => {
		addToast(error?.message || 'Failed to initialize world', 'error');
		setStatus('error');
	}, [addToast]);

	useEffect(() => {
		if (!chatResident) return undefined;
		let frame = null;
		let lastState = null;
		const checkRange = () => {
			const eye = latestPosition.current;
			const residentPosition = residentPositions.current.get(chatResident.id);
			if (eye && residentPosition) {
				const state = conversationRangeState(Math.hypot(eye[0] - residentPosition.x, eye[2] - residentPosition.z));
				if (state !== lastState) {
					lastState = state;
					setConversationRange(state);
				}
				if (state === 'ended') {
					addToast(`You walked too far away from ${chatResident.assistant.name}, so the conversation ended`, 'info');
					setConversationRange('ok');
					closeChat();
					return;
				}
			}
			frame = requestAnimationFrame(checkRange);
		};
		frame = requestAnimationFrame(checkRange);
		return () => cancelAnimationFrame(frame);
	}, [chatResident, closeChat, addToast]);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code !== 'KeyM' || isTypingTarget(event.target)) return;
			setMapExpanded((expanded) => !expanded);
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, []);

	const playResidentVoice = useCallback(async (audioBlob) => {
		const playPositional = chatResident && residentVoices.current.get(chatResident.id);
		const duration = playPositional ? await playPositional(audioBlob) : null;
		if (duration !== null) return duration;
		const url = URL.createObjectURL(audioBlob);
		const player = new Audio(url);
		player.addEventListener('ended', () => URL.revokeObjectURL(url), { once: true });
		await player.play();
		return Number.isFinite(player.duration) ? player.duration : 0;
	}, [chatResident]);

	const activeSession = sessionId && String(session?.id) === String(sessionId) ? session : null;
	const isRequestedSessionLoaded = sessionId ? activeSession !== null : session === null;

	if (status === 'error') return <div className="flex h-full items-center justify-center bg-bg-0"><button className="button-primary" onClick={exit}>RETURN TO WORLDS</button></div>;
	if (!world || !isRequestedSessionLoaded) return <div className="flex h-full items-center justify-center bg-bg-0 text-fg-3 text-sm tracking-[0.1em]">LOADING WORLD...</div>;

	return (
		<div className="flex h-full min-h-0 overflow-hidden bg-black">
			<div className="relative flex-1 min-w-0">
				{chatResident && (
					<div className="absolute left-5 top-16 bottom-5 z-20 w-[min(26rem,40%)] min-w-72">
						<WorldChat world={world} resident={chatResident} onClose={closeChat} addToast={addToast} onPoseTrigger={setActivePose} worldSessionId={sessionId} getPositions={getPositions} onVoiceAudio={playResidentVoice} />
					</div>
				)}
				{chatResident && conversationRange === 'warning' && (
					<div className="absolute left-1/2 top-5 z-20 -translate-x-1/2 border border-line-1 bg-bg-0/90 px-4 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em]">
						YOU ARE WALKING AWAY FROM {chatResident.assistant.name.toUpperCase()}
					</div>
				)}
				<WorldTrackPlayer trackUrl={world.trackUrl} isActive={status === 'ready'} />
				<WorldScene key={`${world.id}:${world.environmentUrl}:${sessionId ?? 'default'}`} world={world} explorationEnabled={status === 'ready'} onReady={handleWorldReady} onError={handleWorldError} onResidentChange={setNearbyResident} onInteract={openChat} activePose={activePose} initialPosition={activeSession?.position} onPlayerPositionChange={handlePlayerPositionChange} residentPositions={residentPositions} residentVoices={residentVoices} activeResidentId={chatResident?.id ?? null} onEndConversation={closeChat} playerView={playerView} offscreenIndicator={offscreenIndicator} onFloorMaps={setFloorMaps} />
				{status === 'ready' && <WorldMap layout={world.layout} floorMaps={floorMaps} playerView={playerView} residents={world.residents} residentPositions={residentPositions} activeResidentId={chatResident?.id ?? null} expanded={mapExpanded} onClose={() => setMapExpanded(false)} />}
				{chatResident && <OffscreenIndicator ref={offscreenIndicator} name={chatResident.assistant.name} />}
				<div className={`absolute inset-0 z-10 flex items-center justify-center overflow-hidden transition-opacity duration-700 ${status !== 'ready' ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}>
					{world.cardImageUrl && (
						<img src={world.cardImageUrl} alt="" className="absolute inset-0 w-full h-full object-cover object-top scale-105 blur-sm brightness-[0.35]" />
					)}
					<div className="absolute inset-0 bg-bg-0/60" />
					<span className="relative text-fg-2 text-sm tracking-[0.12em] animate-fade-in">INITIALIZING {world.name.toUpperCase()}...</span>
				</div>
				<div className="absolute left-5 top-5 z-10 flex items-center gap-3">
					<button type="button" onClick={exit} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">EXIT WORLD</button>
					{nearbyResident && !chatResident && <button type="button" onClick={() => openChat(nearbyResident)} className="button-primary text-[0.7rem]">C — CHAT WITH {nearbyResident.assistant.name.toUpperCase()}</button>}
				</div>
			</div>
		</div>
	);
}
