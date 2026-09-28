import { useState } from 'react';
import { formatAmount } from '../inventoryChanges.js';

function CoinGlyph() {
	return (
		<svg viewBox="0 0 16 16" className="h-3.5 w-3.5 shrink-0 text-accent" fill="none" stroke="currentColor" strokeWidth="1.3" aria-hidden="true">
			<circle cx="8" cy="8" r="6.2" />
			<circle cx="8" cy="8" r="3.6" strokeOpacity="0.55" />
			<path d="M8 5.6v4.8" strokeLinecap="round" />
		</svg>
	);
}

/** The player's credits, always on screen; a change pulses the amount and floats the difference. */
export default function CreditsReadout({ credits }) {
	const [shown, setShown] = useState(credits);
	const [pulse, setPulse] = useState(null);

	if (credits !== shown) {
		if (typeof shown === 'number' && typeof credits === 'number') {
			setPulse((current) => ({ key: (current?.key ?? 0) + 1, delta: credits - shown }));
		}
		setShown(credits);
	}

	if (credits === undefined) return null;

	return (
		<div className="world-hud-panel pointer-events-none relative flex items-center gap-2 px-3 py-1.5">
			<CoinGlyph />
			<span key={pulse?.key ?? 'initial'} className={`world-hud-glow text-[0.8rem] tracking-[0.12em] tabular-nums ${pulse ? 'hud-enter-scale' : ''}`}>{formatAmount(credits)}</span>
			<span className="world-hud-label text-fg-3">CR</span>
			{pulse && pulse.delta !== 0 && (
				<span
					key={`delta-${pulse.key}`}
					className={`absolute -bottom-5 right-2 text-[0.7rem] tracking-[0.1em] tabular-nums ${pulse.delta > 0 ? 'text-accent' : 'text-danger'}`}
					style={{ animation: 'hud-rise-in 0.35s cubic-bezier(0.2, 0.8, 0.2, 1) both, hud-fade-out 0.6s ease-in 1.8s forwards' }}
				>
					{pulse.delta > 0 ? `+${pulse.delta}` : pulse.delta}
				</span>
			)}
		</div>
	);
}
