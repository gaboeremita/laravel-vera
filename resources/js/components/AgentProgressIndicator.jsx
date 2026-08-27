import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

const POLL_INTERVAL_MS = 2000;

export default function AgentProgressIndicator({ assistantId, conversationId, active }) {
	const [status, setStatus] = useState(null);

	// active turning false clears status before the next paint, computed here
	// instead of in the effect below (Constitution Principle VIII).
	const [wasActive, setWasActive] = useState(active);
	if (active !== wasActive) {
		setWasActive(active);
		if (!active) setStatus(null);
	}

	useEffect(() => {
		if (!active) return;

		let cancelled = false;

		const poll = async () => {
			try {
				const res = await api.get(
					route('conversations.agent-progress', { assistant: assistantId, id: conversationId })
				);
				if (!res.ok || cancelled) return;
				const data = await res.json();
				if (!cancelled) setStatus(data.status);
			} catch (err) {
				// The next tick still retries, but the failure itself must be visible —
				// an empty catch here would hide real bugs (Constitution Principle V).
				console.error('[AgentProgressIndicator] poll failed', err);
			}
		};

		void poll();
		const interval = setInterval(poll, POLL_INTERVAL_MS);

		return () => {
			cancelled = true;
			clearInterval(interval);
		};
	}, [active, assistantId, conversationId]);

	if (!active || !status) return null;

	return (
		<div className="text-fg-3 text-[0.7rem] tracking-[0.05em] px-3 py-1 flex items-center gap-2">
			<span className="inline-block w-1.5 h-1.5 rounded-full bg-accent animate-pulse" />
			{status}
		</div>
	);
}
