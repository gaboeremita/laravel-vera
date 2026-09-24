export default function ControlsLegend({ hasZones }) {
	const controls = [['SHIFT', 'RUN'], ['Q', 'CROUCH'], ...(hasZones ? [['G', 'ABOUT THIS PLACE']] : [])];

	return (
		<div className="hud-enter-fade pointer-events-none flex max-w-[13.75rem] flex-wrap items-center justify-end gap-x-3 gap-y-1">
			{controls.map(([key, label]) => (
				<span key={key} className="flex items-center gap-1">
					<span className="world-hud-key">{key}</span>
					<span className="world-hud-label text-fg-3">{label}</span>
				</span>
			))}
		</div>
	);
}
