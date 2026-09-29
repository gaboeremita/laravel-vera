import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

/** The facts the player knows in a session. Any response carrying `learnedFacts` is passed to applyLearnedFacts, which toasts each new one. */
export default function useKnownFacts(worldId, sessionId, addToast) {
	const [knownFacts, setKnownFacts] = useState([]);

	useEffect(() => {
		if (!worldId || !sessionId) return undefined;
		let cancelled = false;
		const load = async () => {
			try {
				const response = await api.get(route('worlds.sessions.known-facts.index', { world: worldId, session: sessionId }));
				if (!response.ok) throw new Error();
				const loaded = await response.json();
				if (!cancelled) setKnownFacts(loaded);
			} catch {
				addToast('Failed to load what you have learned', 'error');
			}
		};
		void load();
		return () => {
			cancelled = true;
		};
	}, [worldId, sessionId, addToast]);

	const applyLearnedFacts = useCallback((learnedFacts) => {
		if (!learnedFacts?.length) return;
		learnedFacts.forEach((fact) => addToast(`You learned something: ${fact.topic}`, 'info'));
		setKnownFacts((current) => [...learnedFacts, ...current.filter((fact) => !learnedFacts.some((learned) => learned.factId === fact.factId))]);
	}, [addToast]);

	return { knownFacts, applyLearnedFacts };
}
