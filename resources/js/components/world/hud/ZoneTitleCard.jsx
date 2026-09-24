import { useEffect, useState } from 'react';

const HOLD_MS = 3000;

/** Announces a zone the way a game names a new area. Remount it with a new key to replay. */
export default function ZoneTitleCard({ zoneName, contextLine, onDone }) {
	const [leaving, setLeaving] = useState(false);

	useEffect(() => {
		const timer = setTimeout(() => setLeaving(true), HOLD_MS);
		return () => clearTimeout(timer);
	}, []);

	return (
		<div className="pointer-events-none absolute left-1/2 top-24 z-20 flex -translate-x-1/2 flex-col items-center text-center">
			<div className={`flex flex-col items-center ${leaving ? 'hud-exit-dissolve' : ''}`} onAnimationEnd={(event) => { if (leaving && event.target === event.currentTarget) onDone(); }}>
				<div className="flex items-center gap-3 world-hud-label hud-enter-fade">
					<span className="h-px w-8 bg-gradient-to-r from-transparent to-accent" />
					<span>ENTERING</span>
					<span className="h-px w-8 bg-gradient-to-l from-transparent to-accent" />
				</div>
				<h2
					className="hud-enter-wipe world-hud-glow mt-2 whitespace-nowrap font-display text-4xl font-semibold uppercase"
					style={{ animation: 'hud-wipe-in 0.75s cubic-bezier(0.2, 0.8, 0.2, 1) both, hud-track-in 1.2s cubic-bezier(0.2, 0.8, 0.2, 1) both, hud-breathe 2.4s ease-in-out 1.2s infinite', letterSpacing: '0.14em' }}
				>
					{zoneName}
				</h2>
				<span className="hud-enter-rule mt-3 block h-px w-72 origin-center bg-gradient-to-r from-transparent via-accent to-transparent shadow-[0_0_12px_var(--accent)]" />
				{contextLine && (
					<span className="hud-enter-fade world-hud-label mt-2" style={{ animationDelay: '150ms' }}>{contextLine}</span>
				)}
			</div>
		</div>
	);
}
