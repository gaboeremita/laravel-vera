export default function WorldCard({ world, onEdit, onEnter }) {
	return (
		<div className="border border-line-1 bg-bg-1 flex flex-col overflow-hidden rounded-md">
			<div className="p-4 flex-1">
				<p className="text-accent text-sm tracking-[0.05em] font-medium truncate">{world.name}</p>
				<p className="text-fg-3 text-xs mt-2 line-clamp-3">{world.description}</p>
			</div>
			<div className="border-t border-line-1 px-4 py-3 flex items-center justify-between">
				<button type="button" onClick={onEdit} className="text-fg-3 text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-fg-1 transition-colors">EDIT</button>
				<button type="button" onClick={onEnter} className="button-primary text-[0.7rem]">ENTER</button>
			</div>
		</div>
	);
}
