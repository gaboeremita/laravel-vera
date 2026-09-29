import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

const EMPTY_STARTING = { player: { credits: 0, items: [] }, residents: {}, objects: {} };

async function fetchConfig(worldId) {
	const [itemsResponse, startingResponse, factsResponse] = await Promise.all([
		api.get(route('worlds.items.index', { world: worldId })),
		api.get(route('worlds.starting-inventories.index', { world: worldId })),
		api.get(route('worlds.facts.index', { world: worldId })),
	]);
	if (!itemsResponse.ok || !startingResponse.ok || !factsResponse.ok) throw new Error();
	const [items, starting, facts] = await Promise.all([itemsResponse.json(), startingResponse.json(), factsResponse.json()]);
	return { items, facts, starting: { player: starting.player, residents: { ...starting.residents }, objects: { ...starting.objects } } };
}

/** A world's items, facts and starting inventories, for its configuration page. */
export default function useWorldInventoryConfig(worldId, addToast) {
	const [items, setItems] = useState([]);
	const [facts, setFacts] = useState([]);
	const [starting, setStarting] = useState(EMPTY_STARTING);

	useEffect(() => {
		let active = true;
		const load = async () => {
			try {
				const config = await fetchConfig(worldId);
				if (!active) return;
				setItems(config.items);
				setFacts(config.facts);
				setStarting(config.starting);
			} catch {
				addToast('Failed to load items', 'error');
			}
		};
		void load();
		return () => { active = false; };
	}, [worldId, addToast]);

	const reload = useCallback(async () => {
		try {
			const config = await fetchConfig(worldId);
			setItems(config.items);
			setFacts(config.facts);
			setStarting(config.starting);
		} catch {
			addToast('Failed to load items', 'error');
		}
	}, [worldId, addToast]);

	/** Saves a starting inventory; resolves to an error message, or null when saved. */
	const saveStarting = useCallback(async (routeName, params, draft, place) => {
		const response = await api.put(route(routeName, { world: worldId, ...params }), draft);
		const body = await response.json().catch(() => ({}));
		if (!response.ok) return body.message || 'Unable to save the starting inventory';
		setStarting((current) => place(current, body));
		addToast('Starting inventory saved', 'success');
		return null;
	}, [worldId, addToast]);

	return { items, facts, reloadItems: reload, starting, saveStarting };
}
