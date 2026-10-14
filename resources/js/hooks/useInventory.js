import { useCallback, useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { inventoryChangeEntries, isReceivedMadeItem } from '../components/world/inventoryChanges.js';
import { playSound } from '../utils/soundCache.js';

/**
 * The player's inventory in a session. Any response carrying `inventory` is
 * passed to applyInventory, which toasts what changed; items a resident made
 * queue up in receivedMadeItems instead, to be shown one at a time.
 */
export default function useInventory(worldId, sessionId, addToast) {
	const [inventory, setInventory] = useState(null);
	const [receivedMadeItems, setReceivedMadeItems] = useState([]);
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
		const entries = inventoryChangeEntries(inventoryRef.current, next);
		const madeItems = entries.filter(isReceivedMadeItem).map((entry) => entry.item);
		if (madeItems.length > 0) setReceivedMadeItems((current) => [...current, ...madeItems]);
		entries.forEach((entry) => {
			if (!isReceivedMadeItem(entry)) addToast(entry.text, 'info', { imageUrl: entry.imageUrl });
			if (entry.delta > 0 && entry.sound) playSound(entry.sound).catch(() => addToast(`Couldn't play the sound for ${entry.text.replace(/^\+\d+ /, '')}`, 'error'));
		});
		inventoryRef.current = next;
		setInventory(next);
	}, [addToast]);

	const dismissReceivedMadeItem = useCallback(() => setReceivedMadeItems((current) => current.slice(1)), []);

	return { inventory, applyInventory, receivedMadeItem: receivedMadeItems[0] ?? null, dismissReceivedMadeItem };
}
