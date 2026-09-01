export default function WorldCard({ world, onEdit, onEnter, onDelete }) {
	return (
		<div className="border border-line-1 bg-bg-1 flex flex-col overflow-hidden rounded-md">
			{world.cardImageUrl && (
				<div className="h-32 w-full overflow-hidden bg-bg-0">
					<img src={world.cardImageUrl} alt="" className="w-full h-full object-cover" />
				</div>
			)}
			<div className="p-4 flex-1">
				<p className="text-accent text-sm tracking-[0.05em] font-medium truncate">{world.name}</p>
				<p className="text-fg-3 text-xs mt-2 line-clamp-3">{world.description}</p>
			</div>
			<div className="border-t border-line-1 px-4 py-3 flex items-center justify-between gap-3">
				<div className="flex items-center gap-3">
					<button type="button" onClick={onEdit} className="text-fg-3 text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-fg-1 transition-colors">EDIT</button>
					<button type="button" onClick={onDelete} className="text-danger/70 text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger transition-colors">DELETE</button>
				</div>
				<button type="button" onClick={onEnter} className="button-primary text-[0.7rem]">ENTER</button>
			</div>
		</div>
	);
}
