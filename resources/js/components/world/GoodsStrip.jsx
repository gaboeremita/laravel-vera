import ItemThumb from '../ItemThumb.jsx';
import { formatAmount } from './inventoryChanges.js';

/** A vendor's items for sale, shown while the player talks with them. */
export default function GoodsStrip({ goods }) {
	if (goods.length === 0) return null;

	return (
		<div className="hud-enter-fade border-b border-line-1 bg-bg-1/40 px-4 py-2">
			<p className="world-hud-label mb-2">FOR SALE</p>
			<div className="flex gap-2 overflow-x-auto custom-scrollbar pb-1">
				{goods.map((item) => (
					<div key={item.itemId} title={item.description} className="flex w-20 shrink-0 flex-col items-center gap-1 border border-line-1 px-1.5 py-2">
						<ItemThumb item={item} size="md" />
						<span className="w-full truncate text-center text-[0.7rem] text-fg-1">{item.name}</span>
						<span className="flex w-full items-center justify-between text-[0.6rem] tracking-[0.08em]">
							<span className="text-fg-3">×{formatAmount(item.quantity)}</span>
							{item.basePrice !== null && <span className="text-accent">{item.basePrice} CR</span>}
						</span>
					</div>
				))}
			</div>
		</div>
	);
}
