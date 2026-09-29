import { useEffect, useState } from 'react';
import { isTypingTarget } from '../keyboardFocus.js';

/** What the player has learned in the session: each fact's topic, source and what they were told. */
export default function LearnedFactsPanel({ knownFacts, onClose }) {
	const [highlighted, setHighlighted] = useState(0);
	const index = Math.min(highlighted, Math.max(0, knownFacts.length - 1));
	const selected = knownFacts[index] ?? null;

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code === 'KeyP' || isTypingTarget(event.target)) return;
			event.stopImmediatePropagation();
			if (event.code === 'KeyJ' || event.code === 'Escape') { event.preventDefault(); if (!event.repeat) onClose(); return; }
			const moves = { ArrowUp: -1, ArrowDown: 1 };
			if (moves[event.code] !== undefined && knownFacts.length > 0) {
				event.preventDefault();
				setHighlighted(Math.min(knownFacts.length - 1, Math.max(0, index + moves[event.code])));
			}
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [knownFacts.length, index, onClose]);

	return (
		<div className="world-pause-backdrop hud-enter-fade absolute inset-0 z-30 flex items-center justify-center p-8" onClick={onClose}>
			<div className="world-hud-panel hud-enter-scale flex max-h-[84%] w-[min(54rem,94%)] flex-col p-6" onClick={(event) => event.stopPropagation()}>
				<div className="flex items-end justify-between gap-6">
					<span className="world-hud-label">LEARNED</span>
					<span className="world-hud-label text-fg-3">{knownFacts.length} {knownFacts.length === 1 ? 'THING' : 'THINGS'}</span>
				</div>
				<span className="hud-enter-rule my-4 block h-px w-full origin-left bg-gradient-to-r from-accent via-accent/40 to-transparent" />

				<div className="min-h-0 flex-1 overflow-y-auto custom-scrollbar pr-1">
					{knownFacts.length === 0 ? (
						<div className="flex flex-col items-center gap-2 py-14">
							<span className="world-hud-label italic text-fg-3">NOTHING LEARNED YET</span>
							<span className="text-fg-3 text-xs">Secrets people share with you, and what you find, show up here.</span>
						</div>
					) : (
						<div className="grid grid-cols-1 gap-6 md:grid-cols-[1fr_20rem]">
							<div role="listbox" className="flex flex-col gap-1.5">
								{knownFacts.map((fact, factIndex) => {
									const active = factIndex === index;
									return (
										<button
											key={fact.factId}
											type="button"
											role="option"
											aria-selected={active}
											onMouseEnter={() => setHighlighted(factIndex)}
											onClick={() => setHighlighted(factIndex)}
											className={`hud-enter-rise flex flex-col items-start gap-0.5 border px-3 py-2 text-left transition-colors cursor-pointer ${active ? 'world-hud-shimmer border-accent/60 bg-accent/10' : 'border-line-1 hover:border-accent/40'}`}
											style={{ animationDelay: `${Math.min(factIndex, 12) * 25}ms`, borderRadius: 0 }}
										>
											<span className={`w-full truncate text-sm ${active ? 'text-fg-1' : 'text-fg-2'}`}>{fact.topic}</span>
											<span className="world-hud-label w-full truncate text-fg-3">FROM {fact.sourceName.toUpperCase()}</span>
										</button>
									);
								})}
							</div>
							{selected && (
								<div key={selected.factId} className="hud-enter-fade flex flex-col border-l border-accent/20 pl-5">
									<h3 className="world-hud-glow font-display text-xl font-semibold uppercase tracking-[0.08em]">{selected.topic}</h3>
									<span className="world-hud-label mt-1 text-fg-3">FROM {selected.sourceName.toUpperCase()}</span>
									<p className="mt-3 text-sm leading-relaxed text-fg-1/85">{selected.summary}</p>
								</div>
							)}
						</div>
					)}
				</div>

				<div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-accent/20 pt-3">
					{knownFacts.length > 0 && <span className="flex items-center gap-1"><span className="world-hud-key">↑ ↓</span><span className="world-hud-label">CHOOSE</span></span>}
					<span className="flex items-center gap-1"><span className="world-hud-key">J</span><span className="world-hud-label">CLOSE</span></span>
				</div>
			</div>
		</div>
	);
}
