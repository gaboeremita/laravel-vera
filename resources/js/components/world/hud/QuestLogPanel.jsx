import { useEffect, useState } from 'react';
import { isTypingTarget } from '../keyboardFocus.js';

const ENDED_LABELS = { completed: 'QUEST COMPLETE', failed: 'QUEST FAILED', abandoned: 'QUEST ABANDONED' };

/** Each quest's latest active or ended run, with the earlier runs of it, grouped under campaigns. */
function groupQuests(runs, campaigns) {
	const shown = runs.filter((run) => run.status !== 'available');
	const byQuest = new Map();
	shown.forEach((run) => byQuest.set(run.questId, [...(byQuest.get(run.questId) ?? []), run]));
	const entries = [...byQuest.values()].map((questRuns) => {
		const sorted = [...questRuns].sort((a, b) => b.run - a.run);
		return { latest: sorted[0], earlier: sorted.slice(1) };
	});
	const groups = campaigns
		.map((campaign) => ({ campaign, entries: entries.filter((entry) => entry.latest.campaignId === campaign.id) }))
		.filter((group) => group.entries.length > 0 || group.campaign.ending);
	const loose = entries.filter((entry) => !campaigns.some((campaign) => campaign.id === entry.latest.campaignId));
	return [...groups, ...(loose.length > 0 ? [{ campaign: null, entries: loose }] : [])];
}

function BeatLine({ beat }) {
	return (
		<span className={`flex items-start gap-2 text-xs leading-snug ${beat.finished ? 'text-fg-3' : beat.current ? 'text-fg-1' : 'text-fg-3/70'}`}>
			<span className={`mt-[0.3rem] h-1.5 w-1.5 shrink-0 rotate-45 border ${beat.finished ? 'border-accent bg-accent' : beat.current ? 'border-accent' : 'border-line-1'}`} />
			<span className={beat.finished ? 'line-through decoration-fg-3/50' : ''}>{beat.text}</span>
		</span>
	);
}

function EndingSummary({ run, onRetry, onShowEnding }) {
	if (run.endingStatus === 'pending') return <p className="world-hud-label text-fg-3 italic">THE ENDING IS BEING WRITTEN</p>;
	if (run.endingStatus === 'failed') {
		return (
			<div className="flex items-center gap-3">
				<p className="text-danger text-[0.7rem] tracking-[0.14em]">THE ENDING COULDN'T BE WRITTEN</p>
				<button type="button" onClick={() => onRetry(run.id)} className="world-hud-panel px-3 py-1 text-fg-1 text-[0.65rem] tracking-[0.14em] hover:text-accent cursor-pointer">WRITE IT AGAIN</button>
			</div>
		);
	}
	if (!run.ending) return null;
	return (
		<button type="button" onClick={() => onShowEnding({ runId: run.id, title: run.title, endingStatus: run.endingStatus, ending: run.ending })} className="w-full border border-accent/40 bg-accent/5 px-3 py-2 text-left cursor-pointer transition-colors hover:bg-accent/10">
			<span className="world-hud-glow block text-sm">{run.ending.title}</span>
			{run.ending.tier && <span className="world-hud-label text-accent">{run.ending.tier.toUpperCase()}</span>}
			<span className="mt-1 block line-clamp-2 text-fg-2 text-xs">{run.ending.epilogue}</span>
		</button>
	);
}

/** The player's quests in the session: what is going on, and how finished quests ended. */
export default function QuestLogPanel({ runs, campaigns, onAbandon, onRetry, onShowEnding, onClose }) {
	const groups = groupQuests(runs, campaigns);
	const entries = groups.flatMap((group) => group.entries);
	const [highlighted, setHighlighted] = useState(0);
	const [confirmingAbandon, setConfirmingAbandon] = useState(false);
	const index = Math.min(highlighted, Math.max(0, entries.length - 1));
	const selected = entries[index] ?? null;

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code === 'KeyP' || isTypingTarget(event.target)) return;
			event.stopImmediatePropagation();
			if (event.code === 'KeyK' || event.code === 'Escape') { event.preventDefault(); if (!event.repeat) onClose(); return; }
			const moves = { ArrowUp: -1, ArrowDown: 1 };
			if (moves[event.code] !== undefined && entries.length > 0) {
				event.preventDefault();
				setConfirmingAbandon(false);
				setHighlighted(Math.min(entries.length - 1, Math.max(0, index + moves[event.code])));
			}
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [entries.length, index, onClose]);

	const select = (entry) => {
		setConfirmingAbandon(false);
		setHighlighted(entries.indexOf(entry));
	};

	return (
		<div className="world-pause-backdrop hud-enter-fade absolute inset-0 z-30 flex items-center justify-center p-8" onClick={onClose}>
			<div className="world-hud-panel hud-enter-scale flex max-h-[84%] w-[min(58rem,94%)] flex-col p-6" onClick={(event) => event.stopPropagation()}>
				<div className="flex items-end justify-between gap-6">
					<span className="world-hud-label">QUESTS</span>
					<span className="world-hud-key">K</span>
				</div>
				<span className="hud-enter-rule my-4 block h-px w-full origin-left bg-gradient-to-r from-accent via-accent/40 to-transparent" />

				<div className="min-h-0 flex-1 overflow-y-auto custom-scrollbar pr-1">
					{entries.length === 0 ? (
						<div className="flex flex-col items-center gap-2 py-14">
							<span className="world-hud-label italic text-fg-3">NO QUESTS YET</span>
							<span className="text-fg-3 text-xs">Quests you take on, and how they end, show up here.</span>
						</div>
					) : (
						<div className="grid grid-cols-1 gap-6 md:grid-cols-[1fr_24rem]">
							<div className="flex flex-col gap-4">
								{groups.map((group) => (
									<div key={group.campaign?.id ?? 'loose'} className="flex flex-col gap-1.5">
										{group.campaign && (
											<button type="button" disabled={!group.campaign.ending} onClick={() => onShowEnding({ campaignId: group.campaign.id, title: group.campaign.title, endingStatus: group.campaign.endingStatus, ending: group.campaign.ending })} className="flex items-center gap-2 text-left disabled:cursor-default cursor-pointer">
												<span className="world-hud-label text-accent">{group.campaign.title.toUpperCase()}</span>
												{group.campaign.ending && <span className="world-hud-label text-fg-3">— {group.campaign.ending.title}</span>}
												<span className="h-px flex-1 bg-line-1" />
											</button>
										)}
										{group.entries.map((entry) => {
											const active = entry === selected;
											const run = entry.latest;
											return (
												<button
													key={run.id}
													type="button"
													onMouseEnter={() => select(entry)}
													onClick={() => select(entry)}
													className={`hud-enter-rise flex flex-col items-start gap-0.5 border px-3 py-2 text-left transition-colors cursor-pointer ${active ? 'world-hud-shimmer border-accent/60 bg-accent/10' : 'border-line-1 hover:border-accent/40'} ${group.campaign ? 'ml-3' : ''}`}
													style={{ borderRadius: 0 }}
												>
													<span className={`w-full truncate text-sm ${active ? 'text-fg-1' : 'text-fg-2'}`}>{run.title}</span>
													{run.status !== 'active' && <span className={`world-hud-label ${run.status === 'completed' ? 'text-accent' : 'text-fg-3'}`}>{ENDED_LABELS[run.status]}</span>}
												</button>
											);
										})}
									</div>
								))}
							</div>

							{selected && (
								<div key={selected.latest.id} className="hud-enter-fade flex flex-col gap-3 border-l border-line-1 pl-5">
									<span className="world-hud-glow font-display text-xl">{selected.latest.title}</span>
									{selected.latest.description && <p className="text-fg-2 text-xs leading-relaxed">{selected.latest.description}</p>}
									<div className="flex flex-col gap-1.5">
										{selected.latest.beats.map((beat) => <BeatLine key={beat.id} beat={beat} />)}
									</div>
									{selected.latest.status === 'active' ? (
										confirmingAbandon ? (
											<div className="flex items-center gap-3 border border-danger/40 bg-danger/5 px-3 py-2">
												<span className="flex-1 text-fg-1 text-xs">Abandon this quest?</span>
												<button type="button" onClick={() => { setConfirmingAbandon(false); void onAbandon(selected.latest.id); }} className="text-danger text-[0.65rem] tracking-[0.14em] cursor-pointer">ABANDON</button>
												<button type="button" onClick={() => setConfirmingAbandon(false)} className="text-fg-3 text-[0.65rem] tracking-[0.14em] hover:text-fg-1 cursor-pointer">CANCEL</button>
											</div>
										) : (
											<button type="button" onClick={() => setConfirmingAbandon(true)} className="self-start text-fg-3 text-[0.65rem] tracking-[0.14em] hover:text-danger cursor-pointer">ABANDON</button>
										)
									) : (
										<EndingSummary run={selected.latest} onRetry={onRetry} onShowEnding={onShowEnding} />
									)}
									{selected.earlier.length > 0 && (
										<div className="flex flex-col gap-2 border-t border-line-1 pt-3">
											{selected.earlier.map((run) => (
												<div key={run.id} className="flex flex-col gap-1">
													<span className="world-hud-label text-fg-3">{ENDED_LABELS[run.status]}</span>
													<EndingSummary run={run} onRetry={onRetry} onShowEnding={onShowEnding} />
												</div>
											))}
										</div>
									)}
								</div>
							)}
						</div>
					)}
				</div>
			</div>
		</div>
	);
}
