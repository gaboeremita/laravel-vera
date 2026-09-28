import { useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import ConfirmationModal from './common/ConfirmationModal.jsx';
import Toggle from './common/Toggle.jsx';
import ItemListField from './ItemListField.jsx';
import ItemThumb from './ItemThumb.jsx';
import UnlimitedAmountInput from './UnlimitedAmountInput.jsx';
import { ImageUploadField } from './WorldImagesEditor.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const NEW_ITEM = { name: '', description: '', basePrice: null, contents: '', useRequirement: '', consumedOnUse: false, releasesCredits: 0, releasesItems: [] };
const HINT = 'normal-case text-fg-3';

function toDraft(item) {
	return { ...NEW_ITEM, ...item, contents: item.contents ?? '', useRequirement: item.useRequirement ?? '', releasesItems: item.releasesItems ?? [] };
}

function toPayload(draft) {
	return {
		name: draft.name.trim(),
		description: draft.description.trim(),
		basePrice: draft.basePrice,
		contents: draft.contents.trim() || null,
		useRequirement: draft.useRequirement.trim() || null,
		consumedOnUse: draft.consumedOnUse,
		releasesCredits: draft.releasesCredits,
		releasesItems: draft.releasesItems,
	};
}

function ItemFields({ draft, setDraft, items, itemId }) {
	const update = (field, value) => setDraft((current) => ({ ...current, [field]: value }));
	const others = items.filter((item) => item.id !== itemId);

	return (
		<div className="space-y-3">
			<div className="grid grid-cols-1 md:grid-cols-[1fr_12rem] gap-3">
				<div>
					<label className={FIELD_LABEL}>Name</label>
					<input value={draft.name} onChange={(event) => update('name', event.target.value)} className={FIELD_INPUT} required />
				</div>
				<div>
					<label className={FIELD_LABEL}>Base price <span className={HINT}>(credits, optional)</span></label>
					<input
						type="number"
						min={0}
						value={draft.basePrice ?? ''}
						onWheel={(event) => event.target.blur()}
						onChange={(event) => update('basePrice', event.target.value === '' ? null : Math.max(0, Math.floor(Number(event.target.value))))}
						className={FIELD_INPUT}
					/>
				</div>
			</div>
			<div>
				<label className={FIELD_LABEL}>Description <span className={HINT}>(what anyone sees at a glance)</span></label>
				<textarea value={draft.description} onChange={(event) => update('description', event.target.value)} rows={2} className={`${FIELD_INPUT} resize-none`} required />
			</div>
			<div className="border-l border-line-1 pl-4 space-y-3">
				<p className="text-fg-3 text-[0.65rem] tracking-[0.15em]">STORY <span className="normal-case tracking-normal">— plain language, judged and narrated by the narrator</span></p>
				<div>
					<label className={FIELD_LABEL}>Examining it reveals</label>
					<textarea value={draft.contents} onChange={(event) => update('contents', event.target.value)} rows={2} placeholder="A letter signed only with an initial, asking to meet at the old pier." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} />
				</div>
				<div>
					<label className={FIELD_LABEL}>Using it takes</label>
					<textarea value={draft.useRequirement} onChange={(event) => update('useRequirement', event.target.value)} rows={2} placeholder="The lockbox opens with the four-digit code its owner chose." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} />
				</div>
				<div className="grid grid-cols-1 md:grid-cols-[12rem_1fr] gap-3 items-start">
					<div>
						<label className={FIELD_LABEL}>Using it gives credits</label>
						<UnlimitedAmountInput value={draft.releasesCredits} allowUnlimited={false} ariaLabel="Credits released" onChange={(value) => update('releasesCredits', value)} />
					</div>
					<div>
						<p className={FIELD_LABEL}>Using it gives items</p>
						<ItemListField items={others} value={draft.releasesItems} onChange={(value) => update('releasesItems', value)} emptyHint="Define other items first." />
					</div>
				</div>
				<label className="flex items-center gap-3 text-fg-2 text-sm">
					<Toggle checked={draft.consumedOnUse} onChange={() => update('consumedOnUse', !draft.consumedOnUse)} />
					<span>Used up when used successfully</span>
				</label>
			</div>
		</div>
	);
}

function ItemRow({ worldId, item, items, onSaved, onDeleted, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [draft, setDraft] = useState(toDraft(item));
	const [previous, setPrevious] = useState(item);
	const [isSaving, setIsSaving] = useState(false);
	const [confirmingDelete, setConfirmingDelete] = useState(false);
	const [isUploading, setIsUploading] = useState(false);

	if (previous !== item) {
		setPrevious(item);
		setDraft(toDraft(item));
	}

	const dirty = JSON.stringify(toPayload(draft)) !== JSON.stringify(toPayload(toDraft(item)));

	const save = async () => {
		setIsSaving(true);
		try {
			const response = await api.patch(route('worlds.items.update', { world: worldId, item: item.id }), toPayload(draft));
			const body = await response.json();
			if (!response.ok) throw new Error(body.message);
			addToast('Item saved', 'success');
			await onSaved();
		} catch (error) { addToast(error.message || 'Failed to save item', 'error'); } finally { setIsSaving(false); }
	};

	const remove = async () => {
		setConfirmingDelete(false);
		try {
			const response = await api.delete(route('worlds.items.destroy', { world: worldId, item: item.id }));
			if (!response.ok) throw new Error();
			addToast(`${item.name} deleted`, 'success');
			await onDeleted();
		} catch { addToast('Failed to delete item', 'error'); }
	};

	const uploadImage = async (file) => {
		setIsUploading(true);
		try {
			const form = new FormData();
			form.append('image', file);
			const response = await api.postForm(route('worlds.items.image.card.store', { world: worldId, item: item.id }), form);
			if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message);
			await onSaved();
		} catch (error) { addToast(error.message || 'Failed to upload image', 'error'); } finally { setIsUploading(false); }
	};

	return (
		<Accordion
			title={item.name}
			collapsed={collapsed}
			onToggle={() => setCollapsed((current) => !current)}
			onDelete={() => setConfirmingDelete(true)}
			badge={
				<span className="flex items-center gap-2">
					<ItemThumb item={item} size="sm" />
					{item.basePrice !== null && <span className="text-fg-3 text-[0.65rem] tracking-[0.1em] border border-line-1 px-1.5 py-0.5">{item.basePrice} CR</span>}
				</span>
			}
		>
			<div className="flex gap-5 items-start">
				<ImageUploadField label="Image" hint="shown in inventories" previewUrl={item.cardImageUrl} isUploading={isUploading} onUpload={uploadImage} />
				<div className="flex-1 min-w-0">
					<ItemFields draft={draft} setDraft={setDraft} items={items} itemId={item.id} />
				</div>
			</div>
			<div className="flex justify-end">
				<button type="button" onClick={save} disabled={!dirty || isSaving || !draft.name.trim() || !draft.description.trim()} className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${!dirty || isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'}`}>
					{isSaving ? 'SAVING...' : 'SAVE ITEM'}
				</button>
			</div>
			{confirmingDelete && (
				<ConfirmationModal
					title="Delete item"
					message={item.usage > 0
						? `Delete "${item.name}"? It is used in ${item.usage} place${item.usage === 1 ? '' : 's'}: it will be removed from every inventory, starting inventory and activity that names it.`
						: `Delete "${item.name}"?`}
					options={[{ label: 'DELETE', value: 'confirm', destructive: true }, { label: 'CANCEL', value: 'cancel', cancel: true }]}
					onSelect={(selected) => { if (selected === 'confirm') void remove(); else setConfirmingDelete(false); }}
				/>
			)}
		</Accordion>
	);
}

function NewItem({ worldId, items, onCreated, onCancel, addToast }) {
	const [draft, setDraft] = useState(NEW_ITEM);
	const [isSaving, setIsSaving] = useState(false);

	const create = async () => {
		setIsSaving(true);
		try {
			const response = await api.post(route('worlds.items.store', { world: worldId }), toPayload(draft));
			const body = await response.json();
			if (!response.ok) throw new Error(body.message);
			addToast(`${body.name} created`, 'success');
			await onCreated();
		} catch (error) { addToast(error.message || 'Failed to create item', 'error'); } finally { setIsSaving(false); }
	};

	return (
		<div className="border border-accent/40 bg-accent/5 p-4 space-y-3 hud-enter-fade">
			<p className="text-accent text-[0.65rem] tracking-[0.15em]">NEW ITEM</p>
			<ItemFields draft={draft} setDraft={setDraft} items={items} itemId={null} />
			<div className="flex justify-end gap-3">
				<button type="button" onClick={onCancel} className="text-fg-3 text-[0.7rem] tracking-[0.1em] px-4 py-1.5 cursor-pointer hover:text-fg-1 transition-colors">CANCEL</button>
				<button type="button" onClick={create} disabled={isSaving || !draft.name.trim() || !draft.description.trim()} className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${isSaving || !draft.name.trim() || !draft.description.trim() ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'}`}>
					{isSaving ? 'CREATING...' : 'CREATE ITEM'}
				</button>
			</div>
		</div>
	);
}

export default function ItemsEditor({ worldId, items, onItemsChange, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [adding, setAdding] = useState(false);

	return (
		<Accordion label="ITEMS" collapsed={collapsed} onToggle={() => setCollapsed((current) => !current)} actions={<span className="text-fg-3 text-xs">{items.length} ITEM{items.length === 1 ? '' : 'S'}</span>}>
			<div className="space-y-2">
				{items.length === 0 && !adding && (
					<div className="border border-dashed border-line-1 px-4 py-6 text-center">
						<p className="text-fg-2 text-sm">No items yet</p>
						<p className="text-fg-3 text-xs mt-1">Items are what characters carry, trade and find in this world.</p>
					</div>
				)}
				{items.map((item) => (
					<ItemRow key={item.id} worldId={worldId} item={item} items={items} onSaved={onItemsChange} onDeleted={onItemsChange} addToast={addToast} />
				))}
				{adding ? (
					<NewItem worldId={worldId} items={items} addToast={addToast} onCancel={() => setAdding(false)} onCreated={async () => { setAdding(false); await onItemsChange(); }} />
				) : (
					<button type="button" onClick={() => setAdding(true)} className="w-full border border-dashed border-line-1 px-3 py-2 text-[0.7rem] tracking-[0.1em] text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer">
						+ NEW ITEM
					</button>
				)}
			</div>
		</Accordion>
	);
}
