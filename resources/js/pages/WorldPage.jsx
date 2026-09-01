import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useOutletContext, useParams, useSearchParams } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import WorldScene from '../components/world/WorldScene.jsx';
import WorldChat from '../components/world/WorldChat.jsx';

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
				setWorld(data);

				if (sessionId) {
					const sessionsResponse = await api.get(route('worlds.sessions.index', { world: worldId }));
					if (sessionsResponse.ok) {
						const sessions = await sessionsResponse.json();
						setSession(sessions.find((s) => String(s.id) === String(sessionId)) ?? null);
					}
				}

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
		if (!sessionId) return undefined;
		const interval = setInterval(persistPosition, 10000);
		return () => {
			clearInterval(interval);
			void persistPosition();
		};
	}, [sessionId, persistPosition]);

	const exit = useCallback(() => {
		persistPosition();
		navigate('/worlds');
	}, [navigate, persistPosition]);
	const openChat = useCallback((resident) => setChatResident(resident), []);
	const closeChat = useCallback(() => setChatResident(null), []);
	const handleWorldReady = useCallback(() => setStatus('ready'), []);
	const handleWorldError = useCallback((error) => {
		addToast(error?.message || 'Failed to initialize world', 'error');
		setStatus('error');
	}, [addToast]);

	useEffect(() => {
		if (!chatResident) return;
		const keyDown = (event) => { if (event.key === 'Escape') closeChat(); };
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [chatResident, closeChat]);

	if (status === 'error') return <div className="flex h-full items-center justify-center bg-bg-0"><button className="button-primary" onClick={exit}>RETURN TO WORLDS</button></div>;
	if (!world) return <div className="flex h-full items-center justify-center bg-bg-0 text-fg-3 text-sm tracking-[0.1em]">LOADING WORLD...</div>;

	return (
		<div className="flex h-full min-h-0 overflow-hidden bg-black">
			{chatResident && (
				<div className="w-[35%] min-w-80 max-w-md shrink-0">
					<WorldChat world={world} resident={chatResident} onClose={closeChat} addToast={addToast} onPoseTrigger={setActivePose} worldSessionId={sessionId} />
				</div>
			)}
			<div className="relative flex-1 min-w-0">
				<WorldScene key={`${world.id}:${world.environmentUrl}`} world={world} explorationEnabled={status === 'ready' && !chatResident} onReady={handleWorldReady} onError={handleWorldError} onResidentChange={setNearbyResident} onInteract={openChat} activePose={activePose} initialPosition={session?.position} onPlayerPositionChange={(position) => { latestPosition.current = position; }} />
				{status !== 'ready' && <div className="absolute inset-0 z-10 flex items-center justify-center bg-bg-0/80 text-fg-2 text-sm tracking-[0.12em]">INITIALIZING {world.name.toUpperCase()}...</div>}
				<div className="absolute left-5 top-5 z-10 flex items-center gap-3">
					<button type="button" onClick={exit} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">EXIT WORLD</button>
					{nearbyResident && !chatResident && <button type="button" onClick={() => openChat(nearbyResident)} className="button-primary text-[0.7rem]">C — CHAT WITH {nearbyResident.assistant.name.toUpperCase()}</button>}
				</div>
			</div>
		</div>
	);
}
