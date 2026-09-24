/** The floating label over the focused object; FocusTracker moves it by writing its style each frame. */
export default function FocusPrompt({ object, labelRef, hidden = false }) {
	return (
		<div ref={labelRef} className="pointer-events-none absolute left-0 top-0 z-10 opacity-0 transition-opacity duration-200 will-change-transform">
			{object && !hidden && (
				<div key={object.id} className="hud-enter-wipe flex flex-col items-center">
					<span className="world-hud-glow whitespace-nowrap font-display text-sm font-semibold uppercase tracking-[0.14em]">{object.name}</span>
					<span className="mt-1 flex items-center gap-1.5">
						<span className="world-hud-key">E</span>
						<span className="world-hud-label">INSPECT</span>
					</span>
					<span className="mt-1 h-5 w-px bg-gradient-to-b from-accent to-transparent" />
				</div>
			)}
		</div>
	);
}
