import { useEffect } from 'react';
import { isTypingTarget } from '../keyboardFocus.js';

/**
 * Something a resident made, shown whole: its picture at full size, its
 * name, who made it and what it is. Pass captureKeys as false when the
 * panel underneath handles Enter and Esc for it.
 */
export default function MadeItemCard({ item, onKeep, captureKeys = true }) {
	useEffect(() => {
		if (!captureKeys) return undefined;
		const keyDown = (event) => {
			if (isTypingTarget(event.target) || event.code === 'KeyP') return;
			event.stopImmediatePropagation();
			if (event.repeat) return;
			if (event.code === 'Enter' || event.code === 'NumpadEnter' || event.code === 'Escape') {
				event.preventDefault();
				onKeep();
			}
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [captureKeys, onKeep]);

	return (
		<div className="world-pause-backdrop hud-enter-fade absolute inset-0 z-40 flex items-center justify-center p-8" onClick={(event) => { event.stopPropagation(); onKeep(); }}>
			<div className="flex max-h-full w-[min(56rem,94%)] flex-col items-center text-center" onClick={(event) => event.stopPropagation()}>
				{item.cardImageUrl && (
					<div className="world-hud-panel hud-enter-scale p-2">
						<img src={item.cardImageUrl} alt={item.name} className="block max-h-[62vh] max-w-full object-contain" />
					</div>
				)}
				<h3 className="world-hud-glow hud-enter-rise mt-5 font-display text-2xl font-semibold uppercase tracking-[calc(var(--hud-label-tracking)*0.5)]" style={{ animationDelay: '80ms' }}>{item.name}</h3>
				{item.madeBy && <span className="world-hud-label hud-enter-rise mt-1" style={{ animationDelay: '120ms' }}>by {item.madeBy}</span>}
				<span className="hud-enter-rule mt-4 hud-rule hud-rule-center w-80 origin-center hud-glow-lg" />
				<p className="hud-enter-rise mt-4 max-w-xl text-fg-2 text-sm italic leading-relaxed" style={{ animationDelay: '160ms' }}>{item.description}</p>
				<button type="button" onClick={onKeep} className="world-hud-panel hud-enter-rise mt-6 flex items-center gap-3 px-5 py-2 text-fg-1 text-[0.7rem] hud-text tracking-[calc(var(--hud-label-tracking)*1)] hover:text-accent cursor-pointer" style={{ animationDelay: '200ms' }}>
					<span className="world-hud-key">ENTER</span>
					<span>KEEP</span>
				</button>
			</div>
		</div>
	);
}
