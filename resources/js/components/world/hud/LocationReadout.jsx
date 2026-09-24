export default function LocationReadout({ text }) {
	return (
		<div className="world-hud-panel world-hud-glow-box pointer-events-none flex max-w-[13.75rem] items-center gap-2 px-3 py-1.5">
			<span className="h-1.5 w-1.5 rounded-full bg-accent shadow-[0_0_8px_var(--accent)] world-hud-glow-box" />
			<span key={text} className="hud-enter-fade world-hud-glow truncate text-[0.62rem] tracking-[0.16em] uppercase">{text}</span>
		</div>
	);
}
