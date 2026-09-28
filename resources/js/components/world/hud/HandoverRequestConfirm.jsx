import { useEffect } from 'react';
import ItemThumb from '../../ItemThumb.jsx';
import { isTypingTarget } from '../keyboardFocus.js';

/** A character asks the player for credits or items; nothing moves unless the player accepts. */
export default function HandoverRequestConfirm({ request, isAnswering, onAccept, onDecline }) {
	useEffect(() => {
		const keyDown = (event) => {
			if (isTypingTarget(event.target) || event.code === 'KeyP') return;
			event.stopImmediatePropagation();
			if (event.repeat || isAnswering) return;
			if ((event.code === 'Enter' || event.code === 'NumpadEnter') && request.affordable) onAccept();
			else if (event.code === 'Escape') onDecline();
		};
		window.addEventListener('keydown', keyDown, true);
		return () => window.removeEventListener('keydown', keyDown, true);
	}, [request.affordable, isAnswering, onAccept, onDecline]);

	return (
		<div className="world-pause-backdrop hud-enter-fade absolute inset-0 z-40 flex items-center justify-center">
			<div className="flex max-w-xl flex-col items-center px-6 text-center">
				<div className="world-hud-label hud-enter-fade flex items-center gap-3">
					<span className="h-px w-10 bg-gradient-to-r from-transparent to-accent" />
					<span>A REQUEST</span>
					<span className="h-px w-10 bg-gradient-to-l from-transparent to-accent" />
				</div>
				<p className="hud-enter-rise mt-5 text-fg-1 text-base leading-relaxed tracking-[0.04em]">
					<span className="world-hud-glow text-accent">{request.askedBy}</span> asks you for
				</p>
				<div className="hud-enter-rise mt-4 flex flex-wrap items-stretch justify-center gap-3" style={{ animationDelay: '80ms' }}>
					{request.credits > 0 && (
						<div className="world-hud-panel flex min-w-28 flex-col items-center justify-center gap-1 px-4 py-3">
							<span className="world-hud-glow font-display text-2xl tabular-nums">{request.credits.toLocaleString()}</span>
							<span className="world-hud-label">CREDITS</span>
						</div>
					)}
					{request.items.map((item) => (
						<div key={item.itemId} className="world-hud-panel flex min-w-28 flex-col items-center gap-2 px-4 py-3">
							<ItemThumb item={item} size="lg" />
							<span className="text-fg-1 text-sm">{item.quantity > 1 ? `${item.quantity} × ` : ''}{item.name}</span>
						</div>
					))}
				</div>
				<p className="hud-enter-rise mt-4 text-fg-2 text-sm italic" style={{ animationDelay: '120ms' }}>{request.reason}</p>
				<span className="hud-enter-rule mt-5 block h-px w-80 origin-center bg-gradient-to-r from-transparent via-accent to-transparent shadow-[0_0_12px_var(--accent)]" />
				{!request.affordable && <p className="hud-enter-fade mt-4 text-warning text-[0.7rem] tracking-[0.12em]">YOU DON'T HAVE ALL OF THIS</p>}
				<div className="hud-enter-rise mt-6 flex items-center gap-3" style={{ animationDelay: '200ms' }}>
					<button type="button" onClick={onAccept} disabled={!request.affordable || isAnswering} className="world-hud-panel relative flex items-center gap-3 px-5 py-2 text-fg-1 text-[0.7rem] tracking-[0.16em] hover:text-accent cursor-pointer disabled:opacity-40 disabled:cursor-default disabled:hover:text-fg-1">
						<span className="world-hud-key">ENTER</span>
						<span>{isAnswering ? 'HANDING OVER...' : 'HAND OVER'}</span>
					</button>
					<button type="button" onClick={onDecline} disabled={isAnswering} className="world-hud-panel relative flex items-center gap-3 px-5 py-2 text-fg-2 text-[0.7rem] tracking-[0.16em] hover:text-fg-1 cursor-pointer disabled:opacity-40">
						<span className="world-hud-key">ESC</span>
						<span>DECLINE</span>
					</button>
				</div>
			</div>
		</div>
	);
}
