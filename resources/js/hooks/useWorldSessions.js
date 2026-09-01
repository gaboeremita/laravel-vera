import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

export default function useWorldSessions(worldId, addToast) {
	const [sessions, setSessions] = useState([]);
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		const load = async () => {
			try {
				const response = await api.get(route('worlds.sessions.index', { world: worldId }));
				if (!response.ok) throw new Error();
				setSessions(await response.json());
			} catch {
				addToast('Failed to load sessions', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		void load();
	}, [worldId, addToast]);

	return { sessions, setSessions, isLoading };
}
