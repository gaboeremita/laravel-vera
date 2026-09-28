import { useState } from 'react';
import { Minus, Plus, X } from 'lucide-react';
import ItemThumb from '../ItemThumb.jsx';
import { formatAmount } from './inventoryChanges.js';

/** Picks credits and items from the player's inventory to hand to the character they're talking with. */
export default function GivePanel({ inventory, recipientName, isGiving, onGive, onClose }) {
	const [credits, setCredits] = useState(0);
	const [quantities, setQuantities] = useState({});
	const maxCredits = inventory?.credits ?? 0;
	const items = inventory?.items ?? [];
	const chosen = Object.entries(quantities).filter(([, quantity]) => quantity > 0).map(([itemId, quantity]) => ({ itemId: Number(itemId), quantity }));
	const canGive = !isGiving && (credits > 0 || chosen.length > 0);

	const step = (item, delta) => setQuantities((current) => {
		const next = Math.min(item.quantity ?? Infinity, Math.max(0, (current[item.itemId] ?? 0) + delta));
		return { ...current, [item.itemId]: next };
	});

	return (
		<div className="hud-enter-rise border-t border-accent/30 bg-bg-1/70 px-4 py-3 space-y-3">
			<div className="flex items-center justify-between">
				<span className="world-hud-label">GIVE TO {recipientName.toUpperCase()}</span>
				<button type="button" onClick={onClose} aria-label="Close" className="text-fg-3 hover:text-fg-1 cursor-pointer"><X size={14} /></button>
			</div>
			<div className="flex items-center gap-3">
				<span className="world-hud-label w-16 shrink-0">CREDITS</span>
				<input
					type="range"
					min={0}
					max={maxCredits}
					value={credits}
					disabled={maxCredits === 0}
					onChange={(event) => setCredits(Number(event.target.value))}
					className="flex-1 accent-[var(--accent)]"
					aria-label="Credits to give"
				/>
				<input
					type="number"
					min={0}
					max={maxCredits}
					value={credits}
					onWheel={(event) => event.target.blur()}
					onChange={(event) => setCredits(Math.min(maxCredits, Math.max(0, Math.floor(Number(event.target.value) || 0))))}
					className="w-20 bg-bg-0 border border-line-1 px-2 py-1 text-right text-sm text-accent outline-none focus:border-accent/50"
				/>
			</div>
			{items.length === 0 ? (
				<p className="world-hud-label italic text-fg-3">YOU CARRY NO ITEMS</p>
			) : (
				<div className="max-h-40 overflow-y-auto custom-scrollbar space-y-1 pr-1">
					{items.map((item) => {
						const quantity = quantities[item.itemId] ?? 0;
						return (
							<div key={item.itemId} className={`flex items-center gap-3 border px-2 py-1.5 transition-colors ${quantity > 0 ? 'border-accent/50 bg-accent/10' : 'border-line-1'}`}>
								<ItemThumb item={item} size="sm" />
								<span className="flex-1 min-w-0 truncate text-sm text-fg-1">{item.name}</span>
								<span className="world-hud-label text-fg-3">×{formatAmount(item.quantity)}</span>
								<div className="flex items-center gap-1">
									<button type="button" onClick={() => step(item, -1)} disabled={quantity === 0} aria-label={`One less ${item.name}`} className="p-1 text-fg-3 hover:text-accent disabled:opacity-30 cursor-pointer disabled:cursor-default"><Minus size={12} /></button>
									<span className={`w-6 text-center text-sm ${quantity > 0 ? 'text-accent' : 'text-fg-3'}`}>{quantity}</span>
									<button type="button" onClick={() => step(item, 1)} disabled={item.quantity !== null && quantity >= item.quantity} aria-label={`One more ${item.name}`} className="p-1 text-fg-3 hover:text-accent disabled:opacity-30 cursor-pointer disabled:cursor-default"><Plus size={12} /></button>
								</div>
							</div>
						);
					})}
				</div>
			)}
			<div className="flex justify-end">
				<button type="button" disabled={!canGive} onClick={() => onGive({ credits, items: chosen })} className="button-primary text-[0.7rem] disabled:opacity-40 disabled:cursor-default">
					{isGiving ? 'HANDING OVER...' : 'HAND OVER'}
				</button>
			</div>
		</div>
	);
}
