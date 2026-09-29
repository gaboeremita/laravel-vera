import ItemThumb from '../ItemThumb.jsx';
import { formatAmount } from './inventoryChanges.js';

/** A vendor's items for sale, shown while the player talks with them; picking one asks for it. */
export default function GoodsStrip({ goods, onPick }) {
	if (goods.length === 0) return null;

	return (
		<div className="hud-enter-fade border-b border-line-1 bg-bg-1/40 px-4 py-2">
			<p className="world-hud-label mb-2">FOR SALE <span className="normal-case tracking-normal text-fg-3">— pick one to ask for it</span></p>
			<div className="grid max-h-[15.5rem] grid-cols-[repeat(auto-fill,minmax(6rem,1fr))] gap-2 overflow-y-auto custom-scrollbar pr-1 pb-1">
				{goods.map((item) => (
					<button
						key={item.itemId}
						type="button"
						title={item.description}
						onClick={() => onPick(item)}
						className="group flex flex-col items-center gap-1 border border-line-1 px-1.5 py-2 transition-colors cursor-pointer hover:border-accent/60 hover:bg-accent/10"
					>
						<ItemThumb item={item} size="md" className="transition-transform group-hover:scale-105" />
						<span className="line-clamp-2 min-h-[2lh] w-full text-center text-[0.7rem] leading-tight text-fg-1">{item.name}</span>
						<span className="flex w-full items-center justify-between text-[0.6rem] tracking-[0.08em]">
							<span className="text-fg-3">×{formatAmount(item.quantity)}</span>
							{item.basePrice !== null && <span className="text-accent">{item.basePrice} CR</span>}
						</span>
					</button>
				))}
			</div>
		</div>
	);
}
