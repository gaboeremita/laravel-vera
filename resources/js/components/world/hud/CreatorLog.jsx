/** The latest changes made from the Creator menu, newest first; a new line types in and older ones slide down. */
export default function CreatorLog({ entries }) {
	return (
		<div className="flex flex-col gap-1 border-t border-line-1 pt-3">
			<span className="world-hud-label text-accent">Creator log</span>
			{entries.length === 0 ? (
				<span className="text-fg-3 text-xs">Changes you make here show up in this log.</span>
			) : entries.slice(0, 3).map((entry, index) => (
				<span key={entry.id} className={`text-xs ${index === 0 ? 'hud-enter-type text-fg-1' : 'hud-enter-rise text-fg-2'}`} style={{ opacity: 1 - index * 0.25 }}>
					<span className="mr-2 text-accent">›</span>{entry.text}
				</span>
			))}
		</div>
	);
}
