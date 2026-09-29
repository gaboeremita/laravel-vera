import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import echo from '../echo.js';

function mergeRuns(current, changed) {
	const byId = new Map(changed.map((run) => [run.id, run]));
	const kept = current.map((run) => byId.get(run.id) ?? run);
	return [...kept, ...changed.filter((run) => !current.some((existing) => existing.id === run.id))];
}

/**
 * The session's quests, kept current from the server's broadcasts: runs,
 * campaigns, a queue of notices to announce, and a queue of endings to show.
 */
export default function useQuests(worldId, sessionId, addToast) {
	const [runs, setRuns] = useState([]);
	const [campaigns, setCampaigns] = useState([]);
	const [notices, setNotices] = useState([]);
	const [endings, setEndings] = useState([]);

	useEffect(() => {
		if (!worldId || !sessionId) return undefined;
		let cancelled = false;

		// Everything after this first load arrives over the channel below.
		const load = async () => {
			try {
				const response = await api.get(route('worlds.sessions.quests.index', { world: worldId, session: sessionId }));
				if (!response.ok) throw new Error();
				const loaded = await response.json();
				if (cancelled) return;
				setRuns(loaded.runs);
				setCampaigns(loaded.campaigns);
			} catch {
				addToast('Failed to load your quests', 'error');
			}
		};
		void load();

		const channelName = `world-session.${sessionId}`;
		const channel = echo.private(channelName);
		channel.listen('.quests.updated', (data) => {
			if (cancelled) return;
			setRuns((current) => mergeRuns(current, data.runs));
			const shown = data.notices.filter((notice) => notice.type !== 'questAvailable');
			if (shown.length > 0) setNotices((current) => [...current, ...shown.map((notice) => ({ ...notice, key: crypto.randomUUID() }))]);
		});
		channel.listen('.quests.ending', (data) => {
			if (cancelled) return;
			if (data.runId) setRuns((current) => current.map((run) => (run.id === data.runId ? { ...run, ending: data.ending, endingStatus: data.endingStatus } : run)));
			if (data.campaignId) setCampaigns((current) => current.map((campaign) => (campaign.id === data.campaignId ? { ...campaign, ending: data.ending, endingStatus: data.endingStatus } : campaign)));
			setEndings((current) => [...current, { ...data, key: crypto.randomUUID() }]);
		});

		return () => {
			cancelled = true;
			channel.stopListening('.quests.updated');
			channel.stopListening('.quests.ending');
			echo.leave(channelName);
		};
	}, [worldId, sessionId, addToast]);

	const dismissNotice = useCallback(() => setNotices((current) => current.slice(1)), []);
	const dismissEnding = useCallback(() => setEndings((current) => current.slice(1)), []);
	const applyRun = useCallback((run) => setRuns((current) => mergeRuns(current, [run])), []);

	/** Queues the ending to be written again after it failed. */
	const retryEnding = useCallback(async (runId) => {
		try {
			const response = await api.post(route('worlds.sessions.quest-runs.assess', { world: worldId, session: sessionId, run: runId }), {});
			if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message);
			setRuns((current) => current.map((run) => (run.id === runId ? { ...run, endingStatus: 'pending' } : run)));
		} catch (error) { addToast(error.message || 'Unable to write the ending again', 'error'); }
	}, [worldId, sessionId, addToast]);

	return { runs, campaigns, notice: notices[0] ?? null, dismissNotice, ending: endings[0] ?? null, dismissEnding, applyRun, retryEnding };
}
