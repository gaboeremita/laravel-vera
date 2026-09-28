import { useCallback, useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { inventoryChanges } from '../components/world/inventoryChanges.js';

/** The player's inventory in a session. Any response carrying `inventory` is passed to applyInventory, which toasts what changed. */
export default function useInventory(worldId, sessionId, addToast) {
	const [inventory, setInventory] = useState(null);
	const inventoryRef = useRef(null);

	useEffect(() => {
		if (!worldId || !sessionId) return undefined;
		let cancelled = false;
		const load = async () => {
			try {
				const response = await api.get(route('worlds.sessions.inventory.show', { world: worldId, session: sessionId }));
				if (!response.ok) throw new Error();
				const loaded = await response.json();
				if (cancelled) return;
				inventoryRef.current = loaded;
				setInventory(loaded);
			} catch {
				addToast('Failed to load your inventory', 'error');
			}
		};
		void load();
		return () => {
			cancelled = true;
		};
	}, [worldId, sessionId, addToast]);

	const applyInventory = useCallback((next) => {
		if (!next) return;
		inventoryChanges(inventoryRef.current, next).forEach((line) => addToast(line, 'info'));
		inventoryRef.current = next;
		setInventory(next);
	}, [addToast]);

	return { inventory, applyInventory };
}
