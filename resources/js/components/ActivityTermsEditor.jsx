import { useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import Toggle from './common/Toggle.jsx';
import FactSelect from './FactSelect.jsx';
import ItemListField from './ItemListField.jsx';
import UnlimitedAmountInput from './UnlimitedAmountInput.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const NO_TERMS = { requiredItemId: null, consumesRequired: false, cost: 0, givesCredits: 0, givesItems: [], requirement: '', outcome: '', vendorResidentId: null, revealsFactId: null };
const HINT = 'normal-case text-fg-3';

function toDraft(terms) {
	return { ...NO_TERMS, ...terms, requirement: terms?.requirement ?? '', outcome: terms?.outcome ?? '', givesItems: terms?.givesItems ?? [] };
}

function summary(terms, itemsById, residentsById) {
	if (!terms) return [];
	return [
		terms.vendorResidentId && `VENDOR ${residentsById.get(terms.vendorResidentId)?.assistant.name.toUpperCase() ?? ''}`.trim(),
		terms.requiredItemId && `NEEDS ${itemsById.get(terms.requiredItemId)?.name?.toUpperCase() ?? 'AN ITEM'}`,
		terms.cost > 0 && `${terms.cost} CR`,
		(terms.givesCredits > 0 || terms.givesItems.length > 0) && 'GIVES',
		(terms.requirement || terms.outcome) && 'NARRATED',
		terms.revealsFactId && 'REVEALS A FACT',
	].filter(Boolean);
}

/** What one activity of an object requires, costs and gives. */
export default function ActivityTermsEditor({ worldId, regionId, objectId, activity, terms, items, facts = [], residents = [], onSaved, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [draft, setDraft] = useState(toDraft(terms));
	const [previous, setPrevious] = useState(terms);
	const [isSaving, setIsSaving] = useState(false);
	const itemsById = new Map(items.map((item) => [item.id, item]));
	const residentsById = new Map(residents.map((resident) => [resident.id, resident]));

	if (previous !== terms) {
		setPrevious(terms);
		setDraft(toDraft(terms));
	}

	const update = (field, value) => setDraft((current) => ({ ...current, [field]: value }));
	const dirty = JSON.stringify(draft) !== JSON.stringify(toDraft(terms));
	const params = { world: worldId, region: regionId, object: objectId, activity: activity.id };

	const save = async () => {
		setIsSaving(true);
		try {
			const response = await api.put(route('worlds.regions.activity-terms.update', params), { ...draft, requirement: draft.requirement.trim() || null, outcome: draft.outcome.trim() || null });
			const body = await response.json();
			if (!response.ok) throw new Error(body.message);
			addToast(`${activity.name} terms saved`, 'success');
			await onSaved();
		} catch (error) { addToast(error.message || 'Unable to save the terms', 'error'); } finally { setIsSaving(false); }
	};

	const clear = async () => {
		try {
			const response = await api.delete(route('worlds.regions.activity-terms.destroy', params));
			if (!response.ok) throw new Error();
			addToast(`${activity.name} is free again`, 'success');
			await onSaved();
		} catch { addToast('Unable to clear the terms', 'error'); }
	};

	return (
		<Accordion
			title={activity.name}
			collapsed={collapsed}
			onToggle={() => setCollapsed((current) => !current)}
			badge={
				<span className="flex flex-wrap gap-1">
					{summary(terms, itemsById, residentsById).map((chip) => <span key={chip} className="border border-accent/40 px-1.5 py-0.5 text-[0.6rem] tracking-[0.1em] text-accent">{chip}</span>)}
					{!terms && <span className="text-fg-3 text-[0.6rem] tracking-[0.1em]">FREE</span>}
				</span>
			}
		>
			<div className="grid grid-cols-1 gap-3 md:grid-cols-2">
				<div>
					<label className={FIELD_LABEL}>Requires item</label>
					<div className="flex items-center gap-3">
						<select value={draft.requiredItemId ?? ''} onChange={(event) => update('requiredItemId', event.target.value === '' ? null : Number(event.target.value))} className={`${FIELD_INPUT} flex-1 min-w-0`}>
							<option value="">— nothing —</option>
							{items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
						</select>
						{draft.requiredItemId !== null && (
							<label className="flex shrink-0 items-center gap-2 text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase">
								<Toggle checked={draft.consumesRequired} onChange={() => update('consumesRequired', !draft.consumesRequired)} />
								Used up
							</label>
						)}
					</div>
				</div>
				<div>
					<label className={FIELD_LABEL}>Costs <span className={HINT}>(credits, paid to the object)</span></label>
					<UnlimitedAmountInput value={draft.cost} allowUnlimited={false} ariaLabel="Cost" onChange={(value) => update('cost', value)} />
				</div>
				<div>
					<label className={FIELD_LABEL}>Gives credits <span className={HINT}>(from the object's stock)</span></label>
					<UnlimitedAmountInput value={draft.givesCredits} allowUnlimited={false} ariaLabel="Credits given" onChange={(value) => update('givesCredits', value)} />
				</div>
				<div>
					<p className={FIELD_LABEL}>Gives items <span className={HINT}>(from the object's stock)</span></p>
					<ItemListField items={items} value={draft.givesItems} onChange={(value) => update('givesItems', value)} />
				</div>
			</div>
			<div>
				<label className={FIELD_LABEL}>Vendor <span className={HINT}>(when they are nearby, choosing this activity starts a conversation with them instead)</span></label>
				<select value={draft.vendorResidentId ?? ''} onChange={(event) => update('vendorResidentId', event.target.value === '' ? null : Number(event.target.value))} className={FIELD_INPUT}>
					<option value="">— nobody, it serves itself —</option>
					{residents.map((resident) => <option key={resident.id} value={resident.id}>{resident.assistant.name}</option>)}
				</select>
			</div>
			<div className="border-l border-line-1 pl-4 space-y-3">
				<p className="text-fg-3 text-[0.65rem] tracking-[0.15em]">STORY <span className="normal-case tracking-normal">— plain language, judged and narrated by the narrator</span></p>
				<div>
					<label className={FIELD_LABEL}>Requirement</label>
					<textarea value={draft.requirement} onChange={(event) => update('requirement', event.target.value)} rows={2} placeholder="Opens for anyone who can show they work for the Guild." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} />
				</div>
				<div>
					<label className={FIELD_LABEL}>Outcome</label>
					<textarea value={draft.outcome} onChange={(event) => update('outcome', event.target.value)} rows={2} placeholder="The terminal shows the last message sent from it." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} />
				</div>
				{facts.length > 0 && (
					<div>
						<label className={FIELD_LABEL}>Reveals fact <span className={HINT}>(the player learns it when the activity succeeds)</span></label>
						<FactSelect facts={facts} value={draft.revealsFactId} onChange={(value) => update('revealsFactId', value)} />
					</div>
				)}
			</div>
			<div className="flex justify-end gap-3">
				{terms && <button type="button" onClick={clear} className="text-danger text-[0.7rem] tracking-[0.1em] px-4 py-1.5 cursor-pointer hover:text-danger transition-colors">CLEAR</button>}
				<button type="button" onClick={save} disabled={!dirty || isSaving} className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${!dirty || isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'}`}>
					{isSaving ? 'SAVING...' : 'SAVE TERMS'}
				</button>
			</div>
		</Accordion>
	);
}
