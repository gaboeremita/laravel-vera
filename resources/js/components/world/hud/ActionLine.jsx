import { useEffect, useState } from 'react';

const HOLD_MS = { line: 4000, notice: 3000 };

function Entry({ entry, onExpire }) {
	const [leaving, setLeaving] = useState(false);

	useEffect(() => {
		const timer = setTimeout(() => setLeaving(true), HOLD_MS[entry.kind]);
		return () => clearTimeout(timer);
	}, [entry.kind]);

	const notice = entry.kind === 'notice';
	return (
		<div
			className={`${leaving ? 'hud-exit-fade' : 'hud-enter-rise'} ${notice ? 'world-hud-panel border-warning/50 px-4 py-1.5' : ''}`}
			onAnimationEnd={(event) => { if (leaving && event.target === event.currentTarget) onExpire(entry.id); }}
		>
			{notice ? (
				<span className="text-warning text-[0.68rem] uppercase tracking-[0.14em]">{entry.text}</span>
			) : (
				<span className="hud-enter-type world-hud-glow inline-block text-base italic tracking-[0.02em]">{entry.text}</span>
			)}
		</div>
	);
}

/** Action lines and short notices, stacked at the bottom centre; each leaves on its own. */
export default function ActionLine({ entries, onExpire }) {
	return (
		<div className="pointer-events-none flex flex-col items-center gap-2 text-center">
			{entries.map((entry) => <Entry key={entry.id} entry={entry} onExpire={onExpire} />)}
		</div>
	);
}
