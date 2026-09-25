import { useState } from 'react';

/**
 * A ring that fills while the user does a standing activity. `onComplete`
 * fires when it is full; `onFinished` once it has animated away, whether it
 * filled or was cancelled. It holds while the world is paused.
 */
export default function ActivityProgress({ activityName, durationMs, cancelled = false, paused = false, onComplete, onFinished }) {
	const [filled, setFilled] = useState(false);
	const leaving = filled || cancelled;

	return (
		<div className="pointer-events-none absolute left-1/2 top-1/2 z-20 flex -translate-x-1/2 -translate-y-1/2 flex-col items-center">
			<div
				className={leaving ? (filled ? 'hud-exit-burst' : 'hud-exit-fade') : 'hud-enter-scale'}
				onAnimationEnd={(event) => { if (leaving && event.target === event.currentTarget) onFinished(); }}
			>
				<svg viewBox="0 0 100 100" className="h-24 w-24 overflow-visible drop-shadow-[0_0_10px_var(--accent)]">
					<circle cx="50" cy="50" r="40" fill="none" stroke="color-mix(in oklab, var(--accent) 18%, transparent)" strokeWidth="4" />
					<circle cx="50" cy="50" r="46" fill="none" stroke="color-mix(in oklab, var(--accent) 10%, transparent)" strokeWidth="1" />
					<circle
						cx="50"
						cy="50"
						r="40"
						fill="none"
						stroke="var(--accent)"
						strokeWidth="4"
						strokeLinecap="round"
						pathLength="100"
						strokeDasharray="100"
						transform="rotate(-90 50 50)"
						style={{ animation: `hud-ring-fill ${durationMs}ms linear forwards`, animationPlayState: cancelled || paused ? 'paused' : 'running' }}
						onAnimationEnd={(event) => {
							event.stopPropagation();
							if (cancelled) return;
							setFilled(true);
							onComplete();
						}}
					/>
					<g style={{ transformOrigin: '50px 50px', animation: `hud-ring-spin ${durationMs}ms linear forwards`, animationPlayState: cancelled || filled || paused ? 'paused' : 'running' }}>
						<circle cx="50" cy="10" r="3.5" fill="var(--accent)" />
						<circle cx="50" cy="10" r="8" fill="color-mix(in oklab, var(--accent) 25%, transparent)" />
					</g>
				</svg>
			</div>
			<span className={`world-hud-glow mt-3 text-[0.7rem] uppercase tracking-[0.18em] ${leaving ? 'hud-exit-fade' : 'hud-enter-fade'}`}>{activityName}</span>
		</div>
	);
}
