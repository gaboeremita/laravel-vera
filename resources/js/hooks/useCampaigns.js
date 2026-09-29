import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

/** A world's campaigns, for the campaign and quest editors. */
export default function useCampaigns(worldId, addToast) {
	const [campaigns, setCampaigns] = useState([]);

	const fetchCampaigns = useCallback(async () => {
		const response = await api.get(route('worlds.campaigns.index', { world: worldId }));
		if (!response.ok) throw new Error();
		return response.json();
	}, [worldId]);

	useEffect(() => {
		if (!worldId) return undefined;
		let active = true;
		const load = async () => {
			try {
				const loaded = await fetchCampaigns();
				if (active) setCampaigns(loaded);
			} catch {
				addToast('Failed to load campaigns', 'error');
			}
		};
		void load();
		return () => { active = false; };
	}, [worldId, fetchCampaigns, addToast]);

	const reload = useCallback(async () => {
		try {
			setCampaigns(await fetchCampaigns());
		} catch {
			addToast('Failed to load campaigns', 'error');
		}
	}, [fetchCampaigns, addToast]);

	return { campaigns, reload };
}
