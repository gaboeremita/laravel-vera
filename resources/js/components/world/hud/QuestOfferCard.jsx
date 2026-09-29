import { useEffect } from 'react';
import { isTypingTarget } from '../keyboardFocus.js';

/** A giver offers the player a quest: its title, description and first beats, to accept or leave for now. */
export default function QuestOfferCard({ offer, isAnswering, onAccept, onDecline }) {
	useEffect(() => {
		const keyDown = (event) => {
			if (isTypingTarget(event.target) || event.code === 'KeyP') return;
			event.stopImmediatePropagation();
			if (event.repeat || isAnswering) return;
			if (event.code === 'Enter' || event.code === 'NumpadEnter') onAccept();
			else if (event.code === 'Escape') onDecline();
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [isAnswering, onAccept, onDecline]);

	return (
		<div className="world-pause-backdrop hud-enter-fade absolute inset-0 z-40 flex items-center justify-center">
			<div className="flex max-w-xl flex-col items-center px-6 text-center">
				<div className="world-hud-label hud-enter-fade flex items-center gap-3">
					<span className="h-px w-10 bg-gradient-to-r from-transparent to-accent" />
					<span>A QUEST</span>
					<span className="h-px w-10 bg-gradient-to-l from-transparent to-accent" />
				</div>
				<h2 className="hud-enter-wipe world-hud-glow mt-4 font-display text-3xl font-semibold uppercase" style={{ letterSpacing: '0.12em' }}>{offer.questTitle}</h2>
				<p className="hud-enter-rise mt-2 text-fg-3 text-[0.7rem] tracking-[0.14em]" style={{ animationDelay: '80ms' }}>FROM <span className="text-accent">{offer.giver.toUpperCase()}</span></p>
				{offer.description && <p className="hud-enter-rise mt-4 text-fg-1 text-sm leading-relaxed" style={{ animationDelay: '120ms' }}>{offer.description}</p>}
				{offer.beats.length > 0 && (
					<div className="world-hud-panel hud-enter-rise mt-5 flex w-full flex-col gap-1.5 px-4 py-3 text-left" style={{ animationDelay: '160ms' }}>
						{offer.beats.map((beat) => (
							<span key={beat.text} className="flex items-start gap-2 text-fg-2 text-xs leading-snug">
								<span className="mt-[0.3rem] h-1.5 w-1.5 shrink-0 rotate-45 border border-accent" />
								<span>{beat.text}</span>
							</span>
						))}
					</div>
				)}
				<span className="hud-enter-rule mt-5 block h-px w-80 origin-center bg-gradient-to-r from-transparent via-accent to-transparent shadow-[0_0_12px_var(--accent)]" />
				<div className="hud-enter-rise mt-6 flex items-center gap-3" style={{ animationDelay: '200ms' }}>
					<button type="button" onClick={onAccept} disabled={isAnswering} className="world-hud-panel relative flex items-center gap-3 px-5 py-2 text-fg-1 text-[0.7rem] tracking-[0.16em] hover:text-accent cursor-pointer disabled:opacity-40 disabled:cursor-default">
						<span className="world-hud-key">ENTER</span>
						<span>ACCEPT</span>
					</button>
					<button type="button" onClick={onDecline} disabled={isAnswering} className="world-hud-panel relative flex items-center gap-3 px-5 py-2 text-fg-2 text-[0.7rem] tracking-[0.16em] hover:text-fg-1 cursor-pointer disabled:opacity-40">
						<span className="world-hud-key">ESC</span>
						<span>NOT NOW</span>
					</button>
				</div>
			</div>
		</div>
	);
}
