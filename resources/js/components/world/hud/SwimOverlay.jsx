import { useEffect, useRef, useState } from 'react';
import { playSplash } from '../worldSounds.js';

export default function SwimOverlay({ active }) {
	const [shown, setShown] = useState(active);
	const previousActive = useRef(active);

	if (active && !shown) setShown(true);

	useEffect(() => {
		if (previousActive.current === active) return;
		previousActive.current = active;
		try {
			playSplash(active);
		} catch (error) {
			console.error('[SwimOverlay] could not play the splash', error);
		}
	}, [active]);

	if (!shown) return null;

	return (
		<div
			className={`pointer-events-none absolute inset-0 z-[5] ${active ? 'hud-enter-fade' : 'hud-exit-fade'}`}
			style={{ animationDuration: '0.4s' }}
			onAnimationEnd={(event) => { if (!active && event.target === event.currentTarget) setShown(false); }}
		>
			<div className="absolute inset-0" style={{ background: 'radial-gradient(ellipse at center, transparent 50%, color-mix(in oklab, var(--accent) 22%, color-mix(in oklab, var(--bg-0) 60%, transparent)) 100%)' }} />
			<div
				className="world-hud-motion absolute inset-0 opacity-25 mix-blend-screen"
				style={{
					backgroundImage: 'radial-gradient(circle at 30% 40%, color-mix(in oklab, var(--accent) 45%, transparent) 0 2px, transparent 40px), radial-gradient(circle at 70% 60%, color-mix(in oklab, var(--accent) 35%, transparent) 0 1px, transparent 50px)',
					backgroundSize: '160px 120px, 220px 180px',
					animation: 'hud-caustics 9s ease-in-out infinite alternate',
					maskImage: 'radial-gradient(ellipse at center, transparent 45%, black 90%)',
				}}
			/>
		</div>
	);
}
