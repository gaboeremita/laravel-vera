import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const SOURCE_LABELS = { in_character: 'IN CHARACTER', ooc_turn: 'OOC TURN', creator: 'CREATOR', item: 'ITEM', activity: 'ACTIVITY' };

function AttemptRow({ attempt }) {
	return (
		<div className={`border-l-2 pl-3 py-2 ${attempt.approved ? 'border-success' : 'border-danger/70'}`}>
			<div className="flex flex-wrap items-center gap-x-3 gap-y-1">
				<span className={`text-[0.65rem] tracking-[0.1em] ${attempt.approved ? 'text-success' : 'text-danger'}`}>{attempt.approved ? 'APPROVED' : 'REJECTED'}</span>
				<span className="text-fg-1 text-sm">{attempt.factTopic}</span>
				<span className="text-fg-3 text-xs">{attempt.holderName}</span>
				<span className="ml-auto text-fg-3 text-[0.65rem] tracking-[0.1em]">
					{SOURCE_LABELS[attempt.source] ?? attempt.source.toUpperCase()}{attempt.reviewed ? ' · REVIEWED' : ''} · {new Date(attempt.createdAt).toLocaleString()}
				</span>
			</div>
			{attempt.reason && <p className="text-fg-2 text-xs mt-1"><span className="text-fg-3">Reason:</span> {attempt.reason}</p>}
			{attempt.verdict && <p className="text-fg-2 text-xs mt-0.5"><span className="text-fg-3">Verdict:</span> {attempt.verdict}</p>}
		</div>
	);
}

/** Every attempt to make a fact known in one session: its reason, the review's verdict and the outcome. */
export default function RevealLog({ worldId, sessions, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [sessionId, setSessionId] = useState(null);
	const [attempts, setAttempts] = useState(null);
	const selectedId = sessionId ?? sessions[0]?.id ?? null;

	useEffect(() => {
		if (collapsed || selectedId === null) return undefined;
		let active = true;
		const load = async () => {
			try {
				const response = await api.get(route('worlds.sessions.reveal-attempts.index', { world: worldId, session: selectedId }));
				if (!response.ok) throw new Error();
				const loaded = await response.json();
				if (active) setAttempts(loaded);
			} catch {
				if (active) addToast('Failed to load the reveal log', 'error');
			}
		};
		void load();
		return () => { active = false; };
	}, [collapsed, worldId, selectedId, addToast]);

	if (sessions.length === 0) return null;

	return (
		<div className="px-4 pb-6">
			<Accordion label="REVEAL LOG" collapsed={collapsed} onToggle={() => setCollapsed((current) => !current)}>
				<div className="max-w-sm">
					<label className={FIELD_LABEL}>Session</label>
					<select value={selectedId ?? ''} onChange={(event) => { setAttempts(null); setSessionId(Number(event.target.value)); }} className={FIELD_INPUT}>
						{sessions.map((session) => <option key={session.id} value={session.id}>{session.title || `Session ${session.id}`}</option>)}
					</select>
				</div>
				{attempts === null ? (
					<p className="text-fg-3 text-xs">Loading...</p>
				) : attempts.length === 0 ? (
					<p className="text-fg-3 text-xs border border-dashed border-line-1 px-3 py-3">No one has tried to reveal anything in this session yet.</p>
				) : (
					<div className="space-y-2">
						{attempts.map((attempt) => <AttemptRow key={attempt.id} attempt={attempt} />)}
					</div>
				)}
			</Accordion>
		</div>
	);
}
