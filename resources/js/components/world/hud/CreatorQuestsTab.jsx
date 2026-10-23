import { useEffect, useState } from 'react';
import { api } from '../../../utils/api.js';
import { HudButton } from './HudControls.jsx';

const STATUS_LABELS = { none: 'Not started', available: 'Not started', active: 'Active', completed: 'Completed', failed: 'Failed', abandoned: 'Abandoned' };
const UNDO_STEP_MS = 60;

function BeatRow({ beat, emptying, onToggle }) {
	const [popping, setPopping] = useState(false);
	return (
		<button
			type="button"
			onClick={async () => {
				if (!beat.finished) setPopping(true);
				await onToggle(beat);
			}}
			className="flex items-start gap-2.5 px-1.5 py-1 text-left text-sm transition-colors hover:bg-accent/5"
			style={{ borderRadius: 'calc(var(--hud-radius) / 3)' }}
		>
			<span
				onAnimationEnd={() => setPopping(false)}
				className={`mt-[0.4rem] h-2 w-2 shrink-0 rotate-45 border transition-colors duration-150 ${beat.finished && !emptying ? 'hud-glow-sm border-accent bg-accent' : 'border-line-2'} ${popping ? 'hud-pop' : ''}`}
			/>
			<span className={beat.finished && !emptying ? 'text-fg-3 line-through decoration-fg-3/50' : 'text-fg-1'}>{beat.text}</span>
		</button>
	);
}

/** Every quest of the world with its state here; the creator starts, ends, reopens and rewrites any of them. */
export default function CreatorQuestsTab({ overview, creator, context, arrowHandlerRef }) {
	const [selectedId, setSelectedId] = useState(overview.quests[0]?.id ?? null);
	const [newFlag, setNewFlag] = useState('');
	const [emptying, setEmptying] = useState([]);
	const index = Math.max(0, overview.quests.findIndex((quest) => quest.id === selectedId));
	const selected = overview.quests[index] ?? null;

	useEffect(() => {
		arrowHandlerRef.current = (step) => {
			const next = overview.quests[Math.min(overview.quests.length - 1, Math.max(0, index + step))];
			if (next) setSelectedId(next.id);
		};
		return () => { arrowHandlerRef.current = null; };
	}, [arrowHandlerRef, overview.quests, index]);

	if (!selected) return <p className="text-fg-3 text-sm">This world has no quests yet.</p>;

	const send = (text, method, name, params = {}, body = undefined) => creator.run(text, () => api[method](context.route(name, { quest: selected.id, ...params }), body));
	const active = selected.status === 'active';
	const ended = ['completed', 'failed', 'abandoned'].includes(selected.status);
	const toggleBeat = async (beat) => {
		const body = await send(`${beat.finished ? 'undid' : 'finished'} "${beat.text}"`, 'put', 'quests.beats.update', { beat: beat.id }, { finished: !beat.finished });
		if (body?.undone?.length) {
			body.undone.forEach((beatId, order) => setTimeout(() => setEmptying((current) => [...current, beatId]), order * UNDO_STEP_MS));
			setTimeout(() => setEmptying([]), body.undone.length * UNDO_STEP_MS + 400);
		}
		return body;
	};

	return (
		<div className="grid grid-cols-[17rem_1fr] gap-5">
			<div className="flex min-w-0 flex-col gap-1.5">
				{overview.quests.map((quest, rowIndex) => {
					const isSelected = quest.id === selected.id;
					return (
						<button
							key={quest.id}
							type="button"
							onClick={() => setSelectedId(quest.id)}
							className={`hud-enter-rise flex flex-col items-start gap-0.5 border px-3 py-2 text-left transition-[border-color,background-color,transform] duration-150 hover:translate-x-0.5 ${isSelected ? 'world-hud-shimmer border-accent/60 bg-accent/10' : 'border-line-1 hover:border-accent/40'}`}
							style={{ borderRadius: 'calc(var(--hud-radius) / 2)', animationDelay: `${Math.min(rowIndex, 12) * 40}ms` }}
						>
							<span className={`w-full truncate text-sm ${isSelected ? 'text-fg-1' : 'text-fg-2'}`}>{quest.title}</span>
							<span className={`world-hud-label ${quest.status === 'active' ? 'text-accent' : 'text-fg-3'}`}>{STATUS_LABELS[quest.status] ?? quest.status}</span>
						</button>
					);
				})}
			</div>

			<div key={selected.id} className="hud-enter-fade flex min-w-0 flex-col gap-4 border-l border-line-1 pl-5">
				<span className="hud-title world-hud-glow text-2xl">{selected.title}</span>
				<div className="flex flex-col">
					<span className="world-hud-label mb-1">Beats{active ? ' · click to finish or undo' : ''}</span>
					{selected.beats.map((beat) => (
						active
							? <BeatRow key={beat.id} beat={beat} emptying={emptying.includes(beat.id)} onToggle={toggleBeat} />
							: <span key={beat.id} className="flex items-start gap-2.5 px-1.5 py-1 text-fg-3 text-sm"><span className={`mt-[0.4rem] h-2 w-2 shrink-0 rotate-45 border ${beat.finished ? 'border-accent bg-accent' : 'border-line-2'}`} />{beat.text}</span>
					))}
				</div>
				<div className="flex flex-col gap-1.5">
					<span className="world-hud-label">Flags</span>
					<div className="flex flex-wrap items-center gap-1.5">
						{selected.flags.map((flag) => (
							<button key={flag} type="button" onClick={() => void send(`cleared the flag ${flag}`, 'put', 'quests.flags.update', { flag }, { on: false })} className="hud-chip hud-enter-fade cursor-pointer text-accent hover:text-danger">
								{flag} ×
							</button>
						))}
						{selected.status !== 'none' && !ended && (
							<form
								onSubmit={async (event) => {
									event.preventDefault();
									if (!newFlag.trim()) return;
									const done = await send(`set the flag ${newFlag.trim()}`, 'put', 'quests.flags.update', { flag: newFlag.trim() }, { on: true });
									if (done) setNewFlag('');
								}}
								className="flex items-center gap-1.5"
							>
								<input value={newFlag} onChange={(event) => setNewFlag(event.target.value)} placeholder="metTheBaker" aria-label="New flag" className="w-36 border border-line-2 bg-bg-1 px-2 py-1 text-fg-1 text-xs" />
								<button type="submit" className="hud-chip cursor-pointer text-fg-3 hover:text-accent">+ Add flag</button>
							</form>
						)}
					</div>
				</div>
				<div className="flex flex-wrap gap-2">
					{!active && <HudButton variant="primary" onClick={() => send(`started ${selected.title}`, 'post', 'quests.start')}>Start</HudButton>}
					{active && <HudButton variant="primary" onClick={() => send(`completed ${selected.title}`, 'post', 'quests.end', {}, { outcome: 'completed' })}>Complete</HudButton>}
					{active && <HudButton variant="danger" onClick={() => send(`failed ${selected.title}`, 'post', 'quests.end', {}, { outcome: 'failed' })}>Fail</HudButton>}
					{selected.status !== 'none' && <HudButton onClick={() => send(`reopened ${selected.title}`, 'post', 'quests.reset')}>Reopen</HudButton>}
				</div>
			</div>
		</div>
	);
}
