import { useEffect, useState } from 'react';
import { isTypingTarget } from '../keyboardFocus.js';

function Scores({ scores }) {
	return (
		<div className="hud-enter-fade mt-4 grid w-full grid-cols-1 gap-2 text-left">
			{scores.map((score) => (
				<div key={score.dimension} className="border border-line-1 px-3 py-2">
					<div className="flex items-center justify-between gap-3">
						<span className="world-hud-label">{score.dimension.toUpperCase()}</span>
						<span className="world-hud-glow font-display text-lg tabular-nums">{score.score}<span className="text-fg-3 text-xs">/10</span></span>
					</div>
					<div className="mt-1 h-0.5 w-full bg-line-1"><div className="h-full bg-accent shadow-[0_0_8px_var(--accent)]" style={{ width: `${score.score * 10}%` }} /></div>
					<p className="mt-1.5 text-fg-2 text-xs leading-relaxed">{score.reason}</p>
				</div>
			))}
		</div>
	);
}

/**
 * How a quest or campaign ended: its title, tier and epilogue, with each
 * score and its reason behind the details toggle. When the ending couldn't
 * be written, it offers to write it again.
 */
export default function EndingCard({ ending, onRetry, onClose }) {
	const [showDetails, setShowDetails] = useState(false);
	const written = ending.endingStatus === 'written' && ending.ending;

	useEffect(() => {
		const keyDown = (event) => {
			if (isTypingTarget(event.target) || event.code === 'KeyP') return;
			event.stopImmediatePropagation();
			if (event.code === 'Escape' && !event.repeat) onClose();
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [onClose]);

	return (
		<div className="world-pause-backdrop hud-enter-fade absolute inset-0 z-40 flex items-center justify-center p-8" onClick={onClose}>
			<div className="relative flex max-h-[86%] w-[min(40rem,94%)] flex-col items-center overflow-y-auto custom-scrollbar px-6 py-8 text-center" onClick={(event) => event.stopPropagation()}>
				<span className="world-hud-key absolute right-2 top-2">ESC</span>
				<div className="world-hud-label hud-enter-fade flex items-center gap-3">
					<span className="h-px w-10 bg-gradient-to-r from-transparent to-accent" />
					<span>{ending.title.toUpperCase()}</span>
					<span className="h-px w-10 bg-gradient-to-l from-transparent to-accent" />
				</div>

				{written ? (
					<>
						<h2 className="hud-enter-wipe world-hud-glow mt-4 font-display text-3xl font-semibold uppercase" style={{ letterSpacing: '0.12em' }}>{ending.ending.title}</h2>
						{ending.ending.tier && <span className="hud-enter-rise mt-3 border border-accent/50 bg-accent/10 px-3 py-1 text-accent text-[0.7rem] tracking-[0.18em]" style={{ animationDelay: '120ms' }}>{ending.ending.tier.toUpperCase()}</span>}
						<span className="hud-enter-rule mt-5 block h-px w-80 origin-center bg-gradient-to-r from-transparent via-accent to-transparent shadow-[0_0_12px_var(--accent)]" />
						<div className="hud-enter-rise mt-5 space-y-3 text-left text-fg-1 text-sm leading-relaxed" style={{ animationDelay: '200ms' }}>
							{ending.ending.epilogue.split(/\n\s*\n/).map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
						</div>
						{ending.ending.scores.length > 0 && (
							<button type="button" onClick={() => setShowDetails((current) => !current)} aria-expanded={showDetails} className="world-hud-panel hud-enter-rise mt-6 flex items-center gap-2 px-4 py-1.5 text-fg-2 text-[0.7rem] tracking-[0.16em] hover:text-accent cursor-pointer" style={{ animationDelay: '260ms' }}>
								<span className={`text-[0.55rem] transition-transform ${showDetails ? 'rotate-90' : ''}`}>▶</span>
								DETAILS
							</button>
						)}
						{showDetails && <Scores scores={ending.ending.scores} />}
					</>
				) : (
					<>
						<p className="hud-enter-rise mt-5 text-danger text-[0.75rem] tracking-[0.16em]">THE ENDING COULDN'T BE WRITTEN</p>
						{onRetry && (
							<button type="button" onClick={onRetry} className="world-hud-panel hud-enter-rise mt-5 px-5 py-2 text-fg-1 text-[0.7rem] tracking-[0.16em] hover:text-accent cursor-pointer" style={{ animationDelay: '120ms' }}>
								WRITE IT AGAIN
							</button>
						)}
					</>
				)}
			</div>
		</div>
	);
}
