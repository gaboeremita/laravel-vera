const SHOWN = 3;

/** The player's active quests and their current beats, always on screen under the credits. */
export default function QuestTracker({ runs }) {
	const active = runs.filter((run) => run.status === 'active');
	if (active.length === 0) return null;
	const shown = active.slice(0, SHOWN);

	return (
		<div className="world-hud-panel pointer-events-none flex w-72 flex-col gap-3 px-4 py-3 hud-enter-fade">
			{shown.map((run) => (
				<div key={run.id} className="flex flex-col gap-1.5">
					<span className="world-hud-glow truncate text-[0.8rem] tracking-[0.08em]">{run.title}</span>
					{run.beats.filter((beat) => beat.current).map((beat) => (
						<span key={beat.id} className="flex items-start gap-2 text-fg-2 text-xs leading-snug">
							<span className="mt-[0.3rem] h-1.5 w-1.5 shrink-0 rotate-45 border border-accent" />
							<span>{beat.text}</span>
						</span>
					))}
				</div>
			))}
			{active.length > SHOWN && <span className="world-hud-label text-fg-3">+{active.length - SHOWN} MORE</span>}
		</div>
	);
}
