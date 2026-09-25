import { useState } from 'react';

const ROW_HEIGHT_REM = 2.75;

const POSTURE_GLYPHS = {
	standing: <><circle cx="8" cy="3" r="1.6" /><path d="M8 5v5M8 10l-2 4M8 10l2 4M5 7h6" /></>,
	sitting: <><circle cx="6" cy="3" r="1.6" /><path d="M6 5v5h5v4M3 10.5h9M6 7h3" /></>,
	reclining: <><circle cx="3.5" cy="5.5" r="1.6" /><path d="M5 7l5 3h4M6 8.5l2-2M2 12h13" /></>,
	lying: <><circle cx="2.5" cy="9" r="1.6" /><path d="M4.5 9h10M6 9l1-2M1 12h14" /></>,
};

function PostureGlyph({ posture }) {
	return (
		<svg viewBox="0 0 16 16" className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
			{POSTURE_GLYPHS[posture] ?? POSTURE_GLYPHS.standing}
		</svg>
	);
}

/**
 * The object or zone card. Pass `card` as null to close it: the last card
 * stays on screen while it animates out.
 */
export default function InspectCard({ card, highlightedIndex, onHighlight, onChoose }) {
	const [shown, setShown] = useState(card);

	if (card && card !== shown) setShown(card);
	if (!shown) return null;

	const closing = !card;
	const { kind, title, contextLine, description, rows } = shown;
	const closeKey = kind === 'zone' ? 'G' : 'E';

	return (
		<div
			className={`world-hud-panel pointer-events-auto absolute right-[15.5rem] top-20 z-20 flex max-h-[calc(100%-7rem)] w-[min(22rem,30vw)] min-w-64 flex-col p-5 ${closing ? 'hud-exit-fade' : 'hud-enter-slide-right'}`}
			onAnimationEnd={(event) => { if (closing && event.target === event.currentTarget) setShown(null); }}
		>
			<span className="world-hud-label">{kind === 'zone' ? 'LOCATION' : 'OBJECT'}</span>
			<h3 className="world-hud-glow mt-1 font-display text-2xl font-semibold uppercase tracking-[0.08em]">{title}</h3>
			{contextLine && <span className="world-hud-label mt-1 text-fg-3">{contextLine}</span>}
			<span className="hud-enter-rule my-3 block h-px w-full origin-left bg-gradient-to-r from-accent via-accent/40 to-transparent" />
			<p className="text-fg-1/85 text-sm leading-relaxed">{description}</p>

			<div className="mt-4 min-h-0 overflow-y-auto">
				{rows.length === 0 ? (
					<span className="world-hud-label italic text-fg-3">NOTHING TO DO HERE</span>
				) : (
					<div role="listbox" className="relative">
						<div
							className="world-hud-shimmer pointer-events-none absolute inset-x-0 top-0 border border-accent/40 bg-accent/10 shadow-[0_0_18px_color-mix(in_oklab,var(--accent)_30%,transparent)] transition-transform duration-[120ms] ease-out"
							style={{ height: `${ROW_HEIGHT_REM}rem`, transform: `translateY(${highlightedIndex * ROW_HEIGHT_REM}rem)` }}
						>
							<span className="absolute inset-y-0 left-0 w-[3px] bg-accent shadow-[0_0_10px_var(--accent)]" />
						</div>
						{rows.map((row, index) => {
							const highlighted = index === highlightedIndex;
							return (
								<button
									key={row.id}
									type="button"
									role="option"
									aria-selected={highlighted}
									onMouseEnter={() => onHighlight(index)}
									onClick={() => onChoose(index)}
									className={`hud-enter-rise relative flex w-full items-center gap-3 px-3 text-left transition-colors duration-[120ms] ${highlighted ? 'text-fg-1' : 'text-fg-2 hover:text-fg-1'}`}
									style={{ height: `${ROW_HEIGHT_REM}rem`, animationDelay: `${index * 40}ms`, borderRadius: 0 }}
								>
									<span className={highlighted ? 'text-accent' : 'text-fg-3'}><PostureGlyph posture={row.posture} /></span>
									<span className="flex-1 truncate text-sm tracking-[0.04em]">{row.name}</span>
									{row.availability && (
										<span className={`world-hud-label ${row.taken ? 'text-warning' : highlighted ? 'text-accent' : ''}`}>{row.availability}</span>
									)}
								</button>
							);
						})}
					</div>
				)}
			</div>

			<div className="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-accent/20 pt-3">
				{rows.length > 0 && (
					<>
						<span className="flex items-center gap-1"><span className="world-hud-key">↑ ↓</span><span className="world-hud-label">CHOOSE</span></span>
						<span className="flex items-center gap-1"><span className="world-hud-key">ENTER</span><span className="world-hud-label">START</span></span>
					</>
				)}
				<span className="flex items-center gap-1"><span className="world-hud-key">{closeKey}</span><span className="world-hud-label">CLOSE</span></span>
			</div>
		</div>
	);
}
