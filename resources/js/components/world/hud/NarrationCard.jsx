import { useEffect, useState } from 'react';
import { X } from 'lucide-react';
import { isTypingTarget } from '../keyboardFocus.js';

const BASE_HOLD_MS = 4500;
const MS_PER_CHARACTER = 45;

/** What the narrator says happened. Remount it with a new key to show another; OK, the close button, Enter or Esc dismiss it. */
export default function NarrationCard({ title, narration, succeeded, changes = [], onDone }) {
	const [leaving, setLeaving] = useState(false);

	useEffect(() => {
		const timer = setTimeout(() => setLeaving(true), BASE_HOLD_MS + narration.length * MS_PER_CHARACTER);
		return () => clearTimeout(timer);
	}, [narration]);

	useEffect(() => {
		if (leaving) return undefined;
		const keyDown = (event) => {
			if (isTypingTarget(event.target) || !['Enter', 'NumpadEnter', 'Escape'].includes(event.code)) return;
			event.preventDefault();
			event.stopImmediatePropagation();
			setLeaving(true);
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [leaving]);

	return (
		<div className="pointer-events-auto absolute left-1/2 top-24 z-30 w-[min(34rem,70vw)] -translate-x-1/2">
			<div
				role="status"
				onAnimationEnd={(event) => { if (leaving && event.target === event.currentTarget) onDone(); }}
				className={`world-hud-panel px-6 py-5 ${leaving ? 'hud-exit-fade' : 'hud-enter-rise'}`}
			>
				<div className="flex items-center gap-3">
					<span className={`h-1.5 w-1.5 rounded-full ${succeeded ? 'bg-accent shadow-[0_0_8px_var(--accent)]' : 'bg-danger shadow-[0_0_8px_var(--color-danger)]'}`} />
					<span className="world-hud-label flex-1">{title}</span>
					<button type="button" onClick={() => setLeaving(true)} aria-label="Close" className="relative z-10 text-fg-3 hover:text-accent transition-colors cursor-pointer">
						<X size={16} />
					</button>
				</div>
				<span className={`hud-enter-rule mt-3 mb-3 block h-px w-full origin-left bg-gradient-to-r ${succeeded ? 'from-accent via-accent/40' : 'from-danger via-danger/40'} to-transparent`} />
				<p className="hud-enter-fade text-fg-1/90 text-sm leading-relaxed whitespace-pre-line" style={{ animationDelay: '120ms' }}>{narration}</p>
				{changes.length > 0 && (
					<div className="mt-4 flex flex-wrap gap-2">
						{changes.map((change) => (
							<span key={change} className={`border px-2 py-0.5 text-[0.65rem] tracking-[0.12em] ${change.startsWith('+') ? 'border-accent/40 text-accent' : 'border-danger/40 text-danger'}`}>{change}</span>
						))}
					</div>
				)}
				<div className="mt-5 flex justify-end">
					<button type="button" onClick={() => setLeaving(true)} className="world-hud-panel relative z-10 flex items-center gap-3 px-5 py-2 text-fg-1 text-[0.7rem] tracking-[0.16em] hover:text-accent cursor-pointer">
						<span className="world-hud-key">ENTER</span>
						<span>OK</span>
					</button>
				</div>
			</div>
		</div>
	);
}
