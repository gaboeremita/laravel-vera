import Accordion from './common/Accordion.jsx';

export default function EntryAccordion({ entry, index, onUpdate, onDelete }) {
	return (
		<Accordion
			label={`ENTRY ${index + 1}`}
			title={entry.title}
			collapsed={entry.collapsed}
			onToggle={() => onUpdate('collapsed', !entry.collapsed)}
			onDelete={onDelete}
		>
			{/* Title */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Title
				</label>
				<input
					type="text"
					value={entry.title}
					onChange={(e) => onUpdate('title', e.target.value)}
					maxLength={100}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
					placeholder="e.g. The Bridge"
				/>
			</div>

			{/* Content */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Content
				</label>
				<textarea
					value={entry.content}
					onChange={(e) => onUpdate('content', e.target.value)}
					rows={5}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
					placeholder="The lore content that will be embedded and injected into prompts..."
				/>
			</div>

			{/* Keywords */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Keywords
					<span className="text-fg-3 ml-2 normal-case">comma-separated</span>
				</label>
				<input
					type="text"
					value={entry.keywords}
					onChange={(e) => onUpdate('keywords', e.target.value)}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
					placeholder="e.g. Bridge, city, digital"
				/>
			</div>

			{/* Tags */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Tags
					<span className="text-fg-3 ml-2 normal-case">comma-separated</span>
				</label>
				<input
					type="text"
					value={entry.tags}
					onChange={(e) => onUpdate('tags', e.target.value)}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
					placeholder="e.g. location, worldbuilding"
				/>
			</div>
		</Accordion>
	);
}