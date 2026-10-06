import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

const EMPTY_OPTIONS = { regions: [], residents: [], sentiments: [], items: [], facts: [], quests: [], campaigns: [] };

/**
 * Everything the quest editor's pickers choose from: the world's regions, residents, sentiments, items, facts, quests and campaigns.
 * A new worldVersion loads them again, for changes saved on the world itself.
 */
export default function useQuestOptions(worldId, addToast, worldVersion = 0) {
	const [options, setOptions] = useState(EMPTY_OPTIONS);

	const fetchOptions = useCallback(async () => {
		const response = await api.get(route('worlds.quest-options', { world: worldId }));
		if (!response.ok) throw new Error();
		return response.json();
	}, [worldId]);

	useEffect(() => {
		if (!worldId) return undefined;
		let active = true;
		const load = async () => {
			try {
				const loaded = await fetchOptions();
				if (active) setOptions(loaded);
			} catch {
				addToast('Failed to load the quest editor\'s choices', 'error');
			}
		};
		void load();
		return () => { active = false; };
	}, [worldId, fetchOptions, addToast, worldVersion]);

	const reload = useCallback(async () => {
		try {
			setOptions(await fetchOptions());
		} catch {
			addToast('Failed to load the quest editor\'s choices', 'error');
		}
	}, [fetchOptions, addToast]);

	return { options, reload };
}
