import { X } from 'lucide-react';
import ItemThumb from './ItemThumb.jsx';
import UnlimitedAmountInput from './UnlimitedAmountInput.jsx';
import { FIELD_INPUT } from '../utils/formFieldStyles.js';

/** A short list of items with quantities, e.g. what an activity or an item gives. */
export default function ItemListField({ items, value, onChange, emptyHint = 'Define items first.' }) {
	const unused = items.filter((item) => !value.some((entry) => entry.itemId === item.id));
	const update = (index, changes) => onChange(value.map((entry, i) => (i === index ? { ...entry, ...changes } : entry)));

	return (
		<div className="space-y-2">
			{value.map((entry, index) => (
				<div key={entry.itemId} className="flex items-center gap-2">
					<ItemThumb item={items.find((item) => item.id === entry.itemId)} size="sm" />
					<select value={entry.itemId} onChange={(event) => update(index, { itemId: Number(event.target.value) })} className={`${FIELD_INPUT} flex-1 min-w-0`}>
						{items.filter((item) => item.id === entry.itemId || unused.includes(item)).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
					</select>
					<div className="w-24 shrink-0">
						<UnlimitedAmountInput value={entry.quantity} min={1} allowUnlimited={false} ariaLabel="Quantity" onChange={(quantity) => update(index, { quantity })} />
					</div>
					<button type="button" aria-label="Remove" onClick={() => onChange(value.filter((_, i) => i !== index))} className="text-fg-3 hover:text-danger transition-colors cursor-pointer"><X size={16} /></button>
				</div>
			))}
			{unused.length > 0 ? (
				<button type="button" onClick={() => onChange([...value, { itemId: unused[0].id, quantity: 1 }])} className="text-info text-[0.65rem] tracking-[0.1em] cursor-pointer hover:text-fg-1 transition-colors">+ ADD ITEM</button>
			) : value.length === 0 && <p className="text-fg-3 text-xs">{emptyHint}</p>}
		</div>
	);
}
