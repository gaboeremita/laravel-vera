import { useState } from 'react';

export default function PostureHint({ hint }) {
	const [shownHint, setShownHint] = useState(hint);

	if (hint && hint !== shownHint) setShownHint(hint);
	if (!shownHint) return null;

	const [key, label] = shownHint.split(' — ');
	return (
		<div
			className={`world-hud-panel pointer-events-none flex items-center gap-2 px-4 py-2 ${hint ? 'hud-enter-rise' : 'hud-exit-fade'}`}
			onAnimationEnd={(event) => { if (!hint && event.target === event.currentTarget) setShownHint(null); }}
		>
			<span className="world-hud-key">{key}</span>
			<span className="world-hud-label">{label}</span>
		</div>
	);
}
