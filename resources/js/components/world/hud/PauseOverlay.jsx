import { useState } from 'react';

/**
 * The frozen world behind a dimmed, themed PAUSED title. It stays mounted
 * while it dissolves away on resume.
 */
export default function PauseOverlay({ paused, onResume, onExit }) {
	const [shown, setShown] = useState(paused);
	if (paused && !shown) setShown(true);
	if (!shown) return null;
	const leaving = !paused;

	return (
		<div
			className={`world-pause-backdrop absolute inset-0 z-40 flex items-center justify-center ${leaving ? 'hud-exit-pause' : 'hud-enter-fade'}`}
			onAnimationEnd={(event) => { if (leaving && event.target === event.currentTarget) setShown(false); }}
		>
			<div className="flex flex-col items-center text-center">
				<div className="world-hud-label hud-enter-fade flex items-center gap-3">
					<span className="h-px w-10 bg-gradient-to-r from-transparent to-accent" />
					<span>WORLD</span>
					<span className="h-px w-10 bg-gradient-to-l from-transparent to-accent" />
				</div>
				<h2 className="world-pause-title hud-enter-wipe world-hud-glow mt-3 font-display text-6xl font-semibold uppercase">
					Paused
				</h2>
				<span className="hud-enter-rule mt-4 block h-px w-80 origin-center bg-gradient-to-r from-transparent via-accent to-transparent shadow-[0_0_12px_var(--accent)]" />
				<p className="world-pause-hint hud-enter-rise mt-4 flex items-center gap-2" style={{ animationDelay: '200ms' }}>
					<span className="world-hud-key">P</span>
					<span>to resume</span>
				</p>
				<div className="hud-enter-rise mt-8 flex items-center gap-3" style={{ animationDelay: '300ms' }}>
					<button type="button" onClick={onResume} className="world-hud-panel relative px-5 py-2 text-fg-1 text-[0.7rem] tracking-[0.16em] hover:text-accent cursor-pointer">RESUME</button>
					<button type="button" onClick={onExit} className="world-hud-panel relative px-5 py-2 text-fg-2 text-[0.7rem] tracking-[0.16em] hover:text-fg-1 cursor-pointer">EXIT WORLD</button>
				</div>
			</div>
		</div>
	);
}
