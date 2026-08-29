import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

const POLL_INTERVAL_MS = 2000;

export default function useAvatarBackground(assistantId, conversationId, active) {
	const [background, setBackground] = useState(null);
	const [inProgress, setInProgress] = useState(false);

	// active turning false stops polling but keeps the last known background
	// visible, computed here instead of in the effect below (Constitution Principle VIII).
	const [wasActive, setWasActive] = useState(active);
	if (active !== wasActive) {
		setWasActive(active);
		if (!active) setInProgress(false);
	}

	useEffect(() => {
		if (!active || !assistantId || !conversationId) return;

		let cancelled = false;

		const poll = async () => {
			try {
				const res = await api.get(
					route('conversations.avatar-background', { assistant: assistantId, id: conversationId })
				);
				if (!res.ok || cancelled) return;
				const data = await res.json();
				if (cancelled) return;
				setInProgress(data.in_progress);
				if (data.background) setBackground(data.background);
			} catch (err) {
				// The next tick still retries, but the failure itself must be visible —
				// an empty catch here would hide real bugs (Constitution Principle V).
				console.error('[useAvatarBackground] poll failed', err);
			}
		};

		void poll();
		const interval = setInterval(poll, POLL_INTERVAL_MS);

		return () => {
			cancelled = true;
			clearInterval(interval);
		};
	}, [active, assistantId, conversationId]);

	return { background, inProgress };
}
