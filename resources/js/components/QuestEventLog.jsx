import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const ENDINGS = ['completed', 'failed', 'abandoned', 'ending_written', 'ending_failed'];

function payloadSummary(payload) {
	return Object.entries(payload ?? {})
		.filter(([, value]) => value !== null && value !== '' && !(Array.isArray(value) && value.length === 0))
		.map(([key, value]) => `${key}: ${typeof value === 'object' ? JSON.stringify(value) : String(value)}`)
		.join(' · ');
}

function EventRow({ event }) {
	const summary = payloadSummary(event.payload);
	const failed = event.type === 'failed' || event.type === 'ending_failed' || (event.type === 'question_judged' && event.payload?.met === false);

	return (
		<div className={`border-l-2 pl-3 py-2 ${failed ? 'border-danger/70' : ENDINGS.includes(event.type) ? 'border-accent' : 'border-line-1'}`}>
			<div className="flex flex-wrap items-center gap-x-3 gap-y-1">
				<span className={`text-[0.65rem] tracking-[0.1em] ${failed ? 'text-danger' : 'text-accent'}`}>{event.type.replaceAll('_', ' ').toUpperCase()}</span>
				<span className="text-fg-1 text-sm">{event.questTitle}{event.run > 1 ? ` · run ${event.run}` : ''}</span>
				{event.beat && <span className="text-fg-3 text-xs">{event.beat}</span>}
				<span className="ml-auto text-fg-3 text-[0.65rem] tracking-[0.1em]">
					{event.byCreator ? 'CREATOR · ' : ''}{new Date(event.createdAt).toLocaleString()}
				</span>
			</div>
			{summary && <p className="text-fg-2 text-xs mt-1 break-words">{summary}</p>}
		</div>
	);
}

/** Every quest event of one session, for understanding an ending or debugging a quest. */
export default function QuestEventLog({ worldId, sessions, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [sessionId, setSessionId] = useState(null);
	const [events, setEvents] = useState(null);
	const selectedId = sessionId ?? sessions[0]?.id ?? null;

	useEffect(() => {
		if (collapsed || selectedId === null) return undefined;
		let active = true;
		const load = async () => {
			try {
				const response = await api.get(route('worlds.sessions.quest-events.index', { world: worldId, session: selectedId }));
				if (!response.ok) throw new Error();
				const loaded = await response.json();
				if (active) setEvents(loaded);
			} catch {
				if (active) addToast('Failed to load the quest log', 'error');
			}
		};
		void load();
		return () => { active = false; };
	}, [collapsed, worldId, selectedId, addToast]);

	if (sessions.length === 0) return null;

	return (
		<div className="px-4 pb-6">
			<Accordion label="QUEST LOG" collapsed={collapsed} onToggle={() => setCollapsed((current) => !current)}>
				<div className="max-w-sm">
					<label className={FIELD_LABEL}>Session</label>
					<select value={selectedId ?? ''} onChange={(event) => { setEvents(null); setSessionId(Number(event.target.value)); }} className={FIELD_INPUT}>
						{sessions.map((session) => <option key={session.id} value={session.id}>{session.title || `Session ${session.id}`}</option>)}
					</select>
				</div>
				{events === null ? (
					<p className="text-fg-3 text-xs">Loading...</p>
				) : events.length === 0 ? (
					<p className="text-fg-3 text-xs border border-dashed border-line-1 px-3 py-3">Nothing has happened in this session's quests yet.</p>
				) : (
					<div className="space-y-2">
						{events.map((event) => <EventRow key={event.id} event={event} />)}
					</div>
				)}
			</Accordion>
		</div>
	);
}
