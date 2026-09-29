import { useState } from 'react';
import { X } from 'lucide-react';
import ItemThumb from './ItemThumb.jsx';
import Toggle from './common/Toggle.jsx';
import UnlimitedAmountInput from './UnlimitedAmountInput.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const EMPTY = { credits: 0, items: [] };

function sameInventory(a, b) {
	return JSON.stringify(a) === JSON.stringify(b);
}

/**
 * Credits and items a holder starts every session with. `flag` adds a per-item toggle:
 * forSale for residents, takeable for objects.
 */
export default function StartingInventoryEditor({ items, value, allowUnlimited, flag = null, flagLabel = '', onSave, saveLabel = 'SAVE' }) {
	const saved = value ?? EMPTY;
	const [draft, setDraft] = useState(saved);
	const [previousSaved, setPreviousSaved] = useState(saved);
	const [isSaving, setIsSaving] = useState(false);
	const [error, setError] = useState(null);

	if (!sameInventory(saved, previousSaved)) {
		setPreviousSaved(saved);
		setDraft(saved);
	}

	const itemsById = new Map(items.map((item) => [item.id, item]));
	const unused = items.filter((item) => !draft.items.some((entry) => entry.itemId === item.id));
	const dirty = !sameInventory(draft, saved);

	const updateEntry = (index, changes) => setDraft((current) => ({ ...current, items: current.items.map((entry, i) => (i === index ? { ...entry, ...changes } : entry)) }));
	const removeEntry = (index) => setDraft((current) => ({ ...current, items: current.items.filter((_, i) => i !== index) }));
	const addEntry = () => {
		if (unused.length === 0) return;
		setDraft((current) => ({ ...current, items: [...current.items, { itemId: unused[0].id, quantity: 1, ...(flag ? { [flag]: false } : {}) }] }));
	};

	const save = async () => {
		setIsSaving(true);
		setError(await onSave(draft));
		setIsSaving(false);
	};

	return (
		<div className="space-y-3">
			<div className="grid grid-cols-[minmax(0,14rem)] gap-1">
				<label className={FIELD_LABEL}>Credits</label>
				<UnlimitedAmountInput value={draft.credits} allowUnlimited={allowUnlimited} ariaLabel="Credits" onChange={(credits) => setDraft((current) => ({ ...current, credits }))} />
			</div>
			<div>
				<p className={FIELD_LABEL}>Items</p>
				{items.length === 0 ? (
					<p className="text-fg-3 text-xs border border-dashed border-line-1 px-3 py-3">This world has no items yet. Define them in the Items section of the World tab.</p>
				) : draft.items.length === 0 ? (
					<p className="text-fg-3 text-xs border border-dashed border-line-1 px-3 py-3">Starts with no items.</p>
				) : (
					<div className="space-y-2">
						{draft.items.map((entry, index) => (
							<div key={entry.itemId} className="flex items-center gap-3 border border-line-1 bg-bg-1/40 p-2">
								<ItemThumb item={itemsById.get(entry.itemId)} size="sm" />
								<select
									value={entry.itemId}
									onChange={(event) => updateEntry(index, { itemId: Number(event.target.value) })}
									className={`${FIELD_INPUT} flex-1 min-w-0`}
									aria-label="Item"
								>
									{[itemsById.get(entry.itemId), ...unused].filter(Boolean).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
								</select>
								<div className="w-32 shrink-0">
									<UnlimitedAmountInput value={entry.quantity} min={1} allowUnlimited={allowUnlimited} ariaLabel="Quantity" onChange={(quantity) => updateEntry(index, { quantity })} />
								</div>
								{flag && (
									<label className="flex items-center gap-2 shrink-0 text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase">
										<Toggle checked={!!entry[flag]} onChange={() => updateEntry(index, { [flag]: !entry[flag] })} />
										{flagLabel}
									</label>
								)}
								<button type="button" onClick={() => removeEntry(index)} aria-label="Remove item" className="text-fg-3 hover:text-danger transition-colors cursor-pointer shrink-0">
									<X size={16} />
								</button>
							</div>
						))}
					</div>
				)}
				{unused.length > 0 && (
					<button type="button" onClick={addEntry} className="mt-2 text-info text-[0.65rem] tracking-[0.1em] cursor-pointer hover:text-fg-1 transition-colors">
						+ ADD ITEM
					</button>
				)}
			</div>
			{error && <p className="text-danger text-xs">{error}</p>}
			<div className="flex justify-end">
				<button
					type="button"
					onClick={save}
					disabled={!dirty || isSaving}
					className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${!dirty || isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'}`}
				>
					{isSaving ? 'SAVING...' : saveLabel}
				</button>
			</div>
		</div>
	);
}
