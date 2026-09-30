import { useState } from 'react';
import { ArrowDown, ArrowUp, X } from 'lucide-react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';
import ConditionBuilder from './ConditionBuilder.jsx';
import FactSelect from './FactSelect.jsx';
import ItemListField from './ItemListField.jsx';
import UnlimitedAmountInput from './UnlimitedAmountInput.jsx';
import { FieldErrors } from './RubricEditor.jsx';
import { ACTIVITY_CONDITION_TYPES } from '../utils/questConditionTypes.js';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const HINT = 'normal-case text-fg-3';
const SMALL_BUTTON = 'text-[0.65rem] tracking-[0.1em] px-2 py-1 border border-dashed border-line-1 text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer';
const ICON_BUTTON = 'text-fg-3 hover:text-accent disabled:opacity-30 disabled:cursor-default transition-colors cursor-pointer';
const CONDITION_CONTEXT = { questKey: null, flags: [], questions: [], beats: [], types: ACTIVITY_CONDITION_TYPES, outsideQuest: true };

const EFFECT_TYPES = [
	{ type: 'giveItems', label: 'Give items', hint: 'from the object\'s stock' },
	{ type: 'takeItems', label: 'Take items', hint: 'from the player, into the object' },
	{ type: 'giveCredits', label: 'Give credits', hint: 'from the object\'s stock' },
	{ type: 'takeCredits', label: 'Take credits', hint: 'from the player, paid to the object' },
	{ type: 'showText', label: 'Show text', hint: 'what happens, which the narrator works into what the player reads' },
	{ type: 'revealFact', label: 'Reveal fact', hint: 'the player learns it' },
	{ type: 'makePassable', label: 'Make passable', hint: 'the object stops blocking the player for the rest of the session' },
];

function newEffect(type, items, facts) {
	switch (type) {
		case 'giveItems':
		case 'takeItems': return { type, items: items[0] ? [{ itemId: items[0].id, quantity: 1 }] : [] };
		case 'giveCredits':
		case 'takeCredits': return { type, amount: 1 };
		case 'showText': return { type, text: '' };
		case 'revealFact': return { type, fact: facts[0]?.id ?? null };
		default: return { type };
	}
}

function toDraft(terms) {
	return { responses: terms?.responses ?? [], vendorResidentId: terms?.vendorResidentId ?? null };
}

function summary(terms, residentsById) {
	if (!terms) return [];
	const count = terms.responses.length;
	return [
		terms.vendorResidentId && `VENDOR ${residentsById.get(terms.vendorResidentId)?.assistant.name.toUpperCase() ?? ''}`.trim(),
		count > 0 && `${count} RESPONSE${count === 1 ? '' : 'S'}`,
	].filter(Boolean);
}

function EffectFields({ effect, onChange, items, facts }) {
	switch (effect.type) {
		case 'giveItems':
		case 'takeItems':
			return <ItemListField items={items} value={effect.items} onChange={(value) => onChange({ ...effect, items: value })} />;
		case 'giveCredits':
		case 'takeCredits':
			return <UnlimitedAmountInput value={effect.amount} min={1} allowUnlimited={false} ariaLabel="Credits" onChange={(amount) => onChange({ ...effect, amount })} />;
		case 'showText':
			return <textarea value={effect.text} onChange={(event) => onChange({ ...effect, text: event.target.value })} rows={2} placeholder="The machine hums and drops a cold can." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} aria-label="Text" />;
		case 'revealFact':
			return <FactSelect facts={facts} value={effect.fact} onChange={(fact) => onChange({ ...effect, fact })} />;
		default:
			return null;
	}
}

function ResponseEditor({ response, index, count, onChange, onMove, onRemove, options, items, facts, errorsAt }) {
	const path = `responses.${index}`;
	const updateEffect = (effectIndex, effect) => onChange({ ...response, effects: response.effects.map((current, position) => (position === effectIndex ? effect : current)) });

	return (
		<div className="border border-line-1 p-3 space-y-3">
			<div className="flex items-center gap-2">
				<p className="text-fg-3 text-[0.65rem] tracking-[0.15em] flex-1">RESPONSE {index + 1}</p>
				<button type="button" onClick={() => onMove(-1)} disabled={index === 0} className={ICON_BUTTON} aria-label="Move up"><ArrowUp size={14} /></button>
				<button type="button" onClick={() => onMove(1)} disabled={index === count - 1} className={ICON_BUTTON} aria-label="Move down"><ArrowDown size={14} /></button>
				<button type="button" onClick={onRemove} className="text-fg-3 hover:text-danger transition-colors cursor-pointer" aria-label="Remove response"><X size={14} /></button>
			</div>
			<div>
				<p className={FIELD_LABEL}>When <span className={HINT}>(no conditions: always)</span></p>
				<ConditionBuilder value={response.condition} onChange={(condition) => onChange({ ...response, condition })} options={options} context={CONDITION_CONTEXT} path={`${path}.condition`} errorsAt={errorsAt} />
			</div>
			<div className="space-y-2">
				<p className={FIELD_LABEL}>Effects</p>
				{response.effects.length === 0 && <p className="text-fg-3 text-xs italic">No effects: the activity just happens.</p>}
				{response.effects.map((effect, effectIndex) => {
					const described = EFFECT_TYPES.find((candidate) => candidate.type === effect.type);
					return (
						<div key={effectIndex} className="border-l-2 border-accent/40 pl-3 space-y-1">
							<div className="flex items-center gap-2">
								<p className="text-accent text-[0.7rem] tracking-[0.1em] uppercase flex-1">{described?.label ?? effect.type} <span className={`${HINT} tracking-normal`}>— {described?.hint}</span></p>
								<button type="button" onClick={() => onChange({ ...response, effects: response.effects.filter((_, position) => position !== effectIndex) })} className="text-fg-3 hover:text-danger transition-colors cursor-pointer" aria-label="Remove effect"><X size={14} /></button>
							</div>
							<EffectFields effect={effect} onChange={(next) => updateEffect(effectIndex, next)} items={items} facts={facts} />
							<FieldErrors messages={errorsAt(`${path}.effects.${effectIndex}`, true)} />
						</div>
					);
				})}
				<select value="" onChange={(event) => event.target.value && onChange({ ...response, effects: [...response.effects, newEffect(event.target.value, items, facts)] })} className={`${FIELD_INPUT} w-auto`} aria-label="Add effect">
					<option value="">+ Add effect</option>
					{EFFECT_TYPES.map((candidate) => <option key={candidate.type} value={candidate.type}>{candidate.label}</option>)}
				</select>
			</div>
		</div>
	);
}

/**
 * What happens when the player uses one activity of an object: the first
 * response whose condition is met runs its effects.
 */
export default function ActivityTermsEditor({ worldId, regionId, objectId, activity, terms, items, facts = [], residents = [], options, onSaved, addToast }) {
	const [collapsed, setCollapsed] = useState(true);
	const [draft, setDraft] = useState(toDraft(terms));
	const [previous, setPrevious] = useState(terms);
	const [errors, setErrors] = useState({});
	const [isSaving, setIsSaving] = useState(false);
	const residentsById = new Map(residents.map((resident) => [resident.id, resident]));

	if (previous !== terms) {
		setPrevious(terms);
		setDraft(toDraft(terms));
		setErrors({});
	}

	const dirty = JSON.stringify(draft) !== JSON.stringify(toDraft(terms));
	const params = { world: worldId, region: regionId, object: objectId, activity: activity.id };
	const errorsAt = (path, nested = false) => Object.entries(errors)
		.filter(([key]) => key === path || (nested && key.startsWith(`${path}.`)))
		.flatMap(([, messages]) => messages);
	const setResponses = (responses) => setDraft((current) => ({ ...current, responses }));
	const moveResponse = (index, offset) => {
		const next = [...draft.responses];
		[next[index], next[index + offset]] = [next[index + offset], next[index]];
		setResponses(next);
	};

	const save = async () => {
		setIsSaving(true);
		try {
			const response = await api.put(route('worlds.regions.activity-terms.update', params), draft);
			const body = await response.json();
			if (response.status === 422) {
				setErrors(body.errors ?? {});
				throw new Error(body.message);
			}
			if (!response.ok) throw new Error(body.message);
			setErrors({});
			addToast(`${activity.name} saved`, 'success');
			await onSaved();
		} catch (error) { addToast(error.message || 'Unable to save the responses', 'error'); } finally { setIsSaving(false); }
	};

	const clear = async () => {
		try {
			const response = await api.delete(route('worlds.regions.activity-terms.destroy', params));
			if (!response.ok) throw new Error();
			addToast(`${activity.name} is free again`, 'success');
			await onSaved();
		} catch { addToast('Unable to clear the responses', 'error'); }
	};

	return (
		<Accordion
			title={activity.name}
			collapsed={collapsed}
			onToggle={() => setCollapsed((current) => !current)}
			badge={
				<span className="flex flex-wrap gap-1">
					{summary(terms, residentsById).map((chip) => <span key={chip} className="border border-accent/40 px-1.5 py-0.5 text-[0.6rem] tracking-[0.1em] text-accent">{chip}</span>)}
					{!terms && <span className="text-fg-3 text-[0.6rem] tracking-[0.1em]">FREE</span>}
				</span>
			}
		>
			<div className="space-y-3">
				<p className="text-fg-3 text-[0.65rem] tracking-[0.15em]">RESPONSES <span className="normal-case tracking-normal">— the first one whose conditions are met runs; with none met the activity is refused, and with none at all it just happens</span></p>
				{draft.responses.map((response, index) => (
					<ResponseEditor
						key={index}
						response={response}
						index={index}
						count={draft.responses.length}
						onChange={(next) => setResponses(draft.responses.map((current, position) => (position === index ? next : current)))}
						onMove={(offset) => moveResponse(index, offset)}
						onRemove={() => setResponses(draft.responses.filter((_, position) => position !== index))}
						options={options}
						items={items}
						facts={facts}
						errorsAt={errorsAt}
					/>
				))}
				<button type="button" onClick={() => setResponses([...draft.responses, { condition: null, effects: [] }])} className={SMALL_BUTTON}>+ RESPONSE</button>
			</div>
			<div>
				<label className={FIELD_LABEL}>Vendor <span className={HINT}>(when they are nearby, choosing this activity starts a conversation with them instead)</span></label>
				<select value={draft.vendorResidentId ?? ''} onChange={(event) => setDraft((current) => ({ ...current, vendorResidentId: event.target.value === '' ? null : Number(event.target.value) }))} className={FIELD_INPUT}>
					<option value="">— nobody, it serves itself —</option>
					{residents.map((resident) => <option key={resident.id} value={resident.id}>{resident.assistant.name}</option>)}
				</select>
				<FieldErrors messages={errors.vendorResidentId} />
			</div>
			<div className="flex justify-end gap-3">
				{terms && <button type="button" onClick={clear} className="text-danger text-[0.7rem] tracking-[0.1em] px-4 py-1.5 cursor-pointer hover:text-danger transition-colors">CLEAR</button>}
				<button type="button" onClick={save} disabled={!dirty || isSaving} className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${!dirty || isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'}`}>
					{isSaving ? 'SAVING...' : 'SAVE'}
				</button>
			</div>
		</Accordion>
	);
}
