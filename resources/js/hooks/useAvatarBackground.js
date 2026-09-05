import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import echo from '../echo.js';

export default function useAvatarBackground(assistantId, conversationId, active) {
	const [background, setBackground] = useState(null);
	const [inProgress, setInProgress] = useState(false);

	// active turning false stops listening but keeps the last known background
	// visible, computed here instead of in the effect below (Constitution Principle VIII).
	const [wasActive, setWasActive] = useState(active);
	if (active !== wasActive) {
		setWasActive(active);
		if (!active) setInProgress(false);
	}

	useEffect(() => {
		if (!active || !assistantId || !conversationId) return;

		let cancelled = false;

		const applyStatus = (data) => {
			if (cancelled) return;
			setInProgress(data.in_progress);
			if (data.background) setBackground(data.background);
		};

		// One-off fetch for the state as of mount (e.g. a generation already
		// finished before this page loaded) — everything after that arrives
		// via the broadcast below instead of polling.
		(async () => {
			try {
				const res = await api.get(
					route('conversations.avatar-background', { assistant: assistantId, id: conversationId })
				);
				if (!res.ok || cancelled) return;
				applyStatus(await res.json());
			} catch (err) {
				console.error('[useAvatarBackground] initial fetch failed', err);
			}
		})();

		const channelName = `conversation.${conversationId}`;
		const channel = echo.private(channelName);
		channel.listen('.avatar-background.updated', applyStatus);

		return () => {
			cancelled = true;
			channel.stopListening('.avatar-background.updated');
			echo.leave(channelName);
		};
	}, [active, assistantId, conversationId]);

	return { background, inProgress };
}
