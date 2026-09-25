import { useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../../utils/api.js';

/**
 * Listening in on two residents talking, without taking part: what they said
 * before the user arrived, then each new line as it is spoken.
 */
export default function ConversationObserverPanel({ worldId, sessionId, conversationId, names, liveLines, residentNames, active, onStop, onClose }) {
	const [history, setHistory] = useState([]);
	const scrollRef = useRef(null);

	useEffect(() => {
		let cancelled = false;
		void (async () => {
			try {
				const params = { world: worldId, session: sessionId, conversation: conversationId };
				await api.post(route('worlds.sessions.conversations.observers.store', params));
				const response = await api.get(route('worlds.sessions.conversations.show', params));
				if (!response.ok) throw new Error(`HTTP ${response.status}`);
				const { data } = await response.json();
				if (!cancelled) setHistory(data.messages.map((message) => ({ id: message.id, name: message.speaker?.name ?? 'Someone', content: message.content })));
			} catch (error) {
				console.error('[ConversationObserverPanel] could not load the conversation', error);
			}
		})();
		return () => { cancelled = true; };
	}, [worldId, sessionId, conversationId, active]);

	const seen = new Set(history.map((line) => line.id));
	const lines = [...history, ...liveLines.filter((line) => !seen.has(line.id) && !String(line.id).startsWith('opening-')).map((line) => ({ id: line.id, name: residentNames.get(line.residentId) ?? 'Someone', content: line.content }))];

	useEffect(() => {
		if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
	}, [lines.length]);

	return (
		<div className="flex h-full flex-col border border-line-1 bg-bg-0/90 backdrop-blur-sm">
			<header className="flex items-center justify-between gap-3 border-b border-line-1 px-4 py-3">
				<div className="min-w-0">
					<p className="text-fg-3 text-[0.6rem] tracking-[0.12em]">{active ? 'LISTENING IN' : 'STOPPED'}</p>
					<p className="truncate text-accent text-sm tracking-[0.05em]">{names.join(' & ')}</p>
				</div>
				<div className="flex items-center gap-3">
					{active && <button type="button" onClick={onStop} className="text-danger text-xs hover:text-danger cursor-pointer">STOP</button>}
					<button type="button" onClick={onClose} className="text-fg-3 text-xs hover:text-fg-1 cursor-pointer">CLOSE (L)</button>
				</div>
			</header>
			<div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto p-4 custom-scrollbar">
				{lines.map((line) => (
					<div key={line.id}>
						<p className="text-accent text-[0.65rem] tracking-[0.1em]">{line.name.toUpperCase()}&gt;</p>
						<p className="whitespace-pre-wrap text-fg-1 text-sm">{line.content}</p>
					</div>
				))}
			</div>
		</div>
	);
}
