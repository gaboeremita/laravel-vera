/** The floating label over the focused object; FocusTracker moves it by writing its style each frame. */
export default function FocusPrompt({ object, labelRef, hidden = false }) {
	return (
		<div ref={labelRef} className="pointer-events-none absolute left-0 top-0 z-10 opacity-0 transition-opacity duration-200 will-change-transform">
			{object && !hidden && (
				<div key={object.id} className="hud-enter-wipe flex flex-col items-center">
					<span className="world-hud-glow whitespace-nowrap font-display text-sm font-semibold uppercase tracking-[0.14em]">{object.name}</span>
					<span className="world-hud-panel mt-2 flex items-center gap-2.5 px-3 py-1.5">
						<span className="relative flex h-8 w-8 items-center justify-center">
							<span className="world-hud-motion absolute inset-0 animate-ping border border-accent/60" />
							<span className="world-hud-glow-box relative flex h-8 w-8 items-center justify-center border border-accent bg-accent/20 font-display text-base font-bold text-accent">E</span>
						</span>
						<span className="world-hud-label text-fg-1">INSPECT</span>
					</span>
					<span className="mt-1 h-6 w-px bg-gradient-to-b from-accent to-transparent" />
				</div>
			)}
		</div>
	);
}
