import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import WorldScene from '../components/world/WorldScene.jsx';
import WorldChat from '../components/world/WorldChat.jsx';

export default function WorldPage() {
	const { worldId } = useParams();
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const [world, setWorld] = useState(null);
	const [status, setStatus] = useState('loading');
	const [nearbyResident, setNearbyResident] = useState(null);
	const [chatResident, setChatResident] = useState(null);

	useEffect(() => {
		const load = async () => {
			try {
				const response = await api.get(route('worlds.show', { world: worldId }));
				if (!response.ok) throw new Error('World unavailable');
				const data = await response.json();
				if (!data.environment_url) throw new Error('This world has no environment asset.');
				setWorld(data);
				setStatus('entering');
			} catch (error) { addToast(error.message || 'Failed to load world', 'error'); setStatus('error'); }
		};
		void load();
	}, [addToast, worldId]);

	const exit = useCallback(() => navigate('/worlds'), [navigate]);
	const openChat = useCallback((resident) => setChatResident(resident), []);
	const closeChat = useCallback(() => setChatResident(null), []);

	if (status === 'error') return <div className="flex h-full items-center justify-center bg-bg-0"><button className="button-primary" onClick={exit}>RETURN TO WORLDS</button></div>;
	if (!world) return <div className="flex h-full items-center justify-center bg-bg-0 text-fg-3 text-sm tracking-[0.1em]">LOADING WORLD...</div>;

	return <div className="relative h-full min-h-0 overflow-hidden bg-black"><WorldScene world={world} explorationEnabled={status === 'ready' && !chatResident} onReady={() => setStatus('ready')} onError={() => setStatus('error')} onResidentChange={setNearbyResident} onInteract={openChat} />{status !== 'ready' && <div className="absolute inset-0 z-10 flex items-center justify-center bg-bg-0/80 text-fg-2 text-sm tracking-[0.12em]">INITIALIZING {world.name.toUpperCase()}...</div>}<div className="absolute left-5 top-5 z-10 flex items-center gap-3"><button type="button" onClick={exit} className="border border-line-1 bg-bg-0/90 px-3 py-2 text-fg-2 text-[0.7rem] tracking-[0.1em] hover:text-fg-1">EXIT WORLD</button>{nearbyResident && !chatResident && <button type="button" onClick={() => openChat(nearbyResident)} className="button-primary text-[0.7rem]">C — CHAT WITH {nearbyResident.assistant.name.toUpperCase()}</button>}</div>{chatResident && <WorldChat world={world} resident={chatResident} onClose={closeChat} addToast={addToast} />}</div>;
}
