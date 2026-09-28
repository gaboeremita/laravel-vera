/** Asks the player to confirm before travelling through a passage. */
export default function PassageConfirm({ fromRegionName, toRegionName, onConfirm, onCancel }) {
	return (
		<div className="world-pause-backdrop hud-enter-fade absolute inset-0 z-40 flex items-center justify-center">
			<div className="flex max-w-xl flex-col items-center px-6 text-center">
				<div className="world-hud-label hud-enter-fade flex items-center gap-3">
					<span className="h-px w-10 bg-gradient-to-r from-transparent to-accent" />
					<span>PASSAGE</span>
					<span className="h-px w-10 bg-gradient-to-l from-transparent to-accent" />
				</div>
				<p className="hud-enter-rise mt-5 text-fg-1 text-base leading-relaxed tracking-[0.04em]">
					You are about to leave <span className="world-hud-glow text-accent">{fromRegionName}</span> and go to <span className="world-hud-glow text-accent">{toRegionName}</span>
				</p>
				<span className="hud-enter-rule mt-5 block h-px w-80 origin-center bg-gradient-to-r from-transparent via-accent to-transparent shadow-[0_0_12px_var(--accent)]" />
				<div className="hud-enter-rise mt-8 flex items-center gap-3" style={{ animationDelay: '200ms' }}>
					<button type="button" onClick={onConfirm} className="world-hud-panel relative flex items-center gap-3 px-5 py-2 text-fg-1 text-[0.7rem] tracking-[0.16em] hover:text-accent cursor-pointer">
						<span className="world-hud-key">ENTER</span>
						<span>CONFIRM</span>
					</button>
					<button type="button" onClick={onCancel} className="world-hud-panel relative flex items-center gap-3 px-5 py-2 text-fg-2 text-[0.7rem] tracking-[0.16em] hover:text-fg-1 cursor-pointer">
						<span className="world-hud-key">ESC</span>
						<span>CANCEL</span>
					</button>
				</div>
			</div>
		</div>
	);
}
