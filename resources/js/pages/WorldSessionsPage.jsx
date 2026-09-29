import { useEffect, useState } from 'react';
import { useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { route } from 'ziggy-js';
import Header from '../components/Header.jsx';
import WorldSessionList from '../components/WorldSessionList.jsx';
import RevealLog from '../components/RevealLog.jsx';
import useWorldSessions from '../hooks/useWorldSessions.js';
import { api } from '../utils/api.js';

export default function WorldSessionsPage() {
	const { worldId } = useParams();
	const navigate = useNavigate();
	const { addToast, setWorldPortraitUrl } = useOutletContext();
	const { sessions, setSessions, isLoading } = useWorldSessions(worldId, addToast);
	const [hasSpawn, setHasSpawn] = useState(true);

	useEffect(() => {
		const load = async () => {
			const response = await api.get(route('worlds.show', { world: worldId }));
			if (!response.ok) return;
			const world = await response.json();
			setWorldPortraitUrl(world.portraitImageUrl || null);
			setHasSpawn(world.hasSpawn);
		};
		void load();
		return () => setWorldPortraitUrl(null);
	}, [worldId, setWorldPortraitUrl]);

	const handleSelect = (id) => {
		navigate(`/worlds/${worldId}?session=${id}`);
	};

	const handleNew = async () => {
		try {
			const res = await api.post(route('worlds.sessions.store', { world: worldId }));
			const data = await res.json();
			if (!res.ok) return addToast(data.message || 'Failed to start a new session', 'error');
			setSessions((prev) => [data, ...prev]);
			navigate(`/worlds/${worldId}?session=${data.id}`);
		} catch {
			addToast('Failed to start a new session', 'error');
		}
	};

	const handleDelete = (id) => {
		setSessions((prev) => prev.filter((s) => s.id !== id));
	};

	const handleRename = async (id, title) => {
		try {
			await api.patch(route('worlds.sessions.update', { world: worldId, session: id }), { title });
			setSessions((prev) =>
				prev.map((s) => (s.id === id ? { ...s, title } : s))
			);
		} catch (error) {
			console.error('Failed to rename session:', error);
		}
	};

	return (
		<>
			<Header
				hideSettings
				status={{ label: isLoading ? 'LOADING' : 'WAITING', color: isLoading ? 'text-warning' : 'text-info', dot: '●', blink: isLoading }}
				counter={!isLoading ? `SESSIONS: ${sessions.length}` : null}
				onBack={() => navigate('/worlds')}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Sessions</span>
			</Header>

			<div className="flex-1 overflow-y-auto">
				{!hasSpawn && <p className="text-warning text-xs px-4 pt-4">⚠ This world has no spawn point yet. Choose one in the world's configuration to start a session.</p>}
				<WorldSessionList
					worldId={worldId}
					sessions={sessions}
					canStartNew={hasSpawn}
					onSelect={handleSelect}
					onNew={handleNew}
					onDelete={handleDelete}
					onRename={handleRename}
				/>
				<RevealLog worldId={worldId} sessions={sessions} addToast={addToast} />
			</div>
		</>
	);
}
