export default function ControlsLegend({ hasZones, hasInventory = false }) {
	const controls = [['R', 'RUN'], ['SPACE', 'JUMP'], ['Q', 'CROUCH'], ...(hasZones ? [['G', 'ABOUT THIS PLACE']] : []), ...(hasInventory ? [['TAB', 'INVENTORY'], ['J', 'LEARNED'], ['K', 'QUESTS']] : []), ['O', 'VOICE'], ['P', 'PAUSE']];

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
