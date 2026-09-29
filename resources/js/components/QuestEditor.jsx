import { useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import ConditionBuilder from './ConditionBuilder.jsx';
import { ALL_CONDITION_TYPES } from '../utils/questConditionTypes.js';
import QuestBeatEditor from './QuestBeatEditor.jsx';
import RubricEditor, { FieldErrors } from './RubricEditor.jsx';
import Toggle from './common/Toggle.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const HINT = 'normal-case text-fg-3';
const SMALL_BUTTON = 'text-[0.65rem] tracking-[0.1em] px-2 py-1 border border-dashed border-line-1 text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer';
const SECTION = 'text-fg-3 text-[0.65rem] tracking-[0.15em] flex items-center gap-3 pt-2';
const OUTCOMES = [['completed', 'Completed'], ['failed', 'Failed'], ['abandoned', 'Abandoned'], ['ended', 'Ended in any way']];
const START_MODES = [['auto', 'With the session'], ['condition', 'On a condition'], ['offer', 'Offered by']];

/** Errors at these definition paths are shown on their field; the rest are listed at the top. */
const FIELD_PATH = /^(description|start|requires\.\d|repeatable|beats\.\d|complete|fail|rubric\.|reward)/;

function newBeat(id = '') {
	return { id, text: '', hidden: false, requires: [], when: null, knowledge: [], grants: [], questions: [] };
}

const NEW_DEFINITION = {
	description: '',
	start: { mode: 'auto' },
	requires: [],
	repeatable: false,
	beats: [newBeat('first')],
	complete: null,
	fail: null,
	rubric: { guidance: '', dimensions: [{ name: '', description: '' }], tiers: [] },
};

function toDraft(quest) {
	return quest
		? { key: quest.key, title: quest.title, campaignId: quest.campaignId ?? null, definition: { ...NEW_DEFINITION, ...quest.definition } }
		: { key: '', title: '', campaignId: null, definition: NEW_DEFINITION };
}

/** The first problem that keeps JSON from being edited in the form, or null. */
function shapeProblem(parsed) {
	if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) return 'The definition must be a JSON object.';
	if (!Array.isArray(parsed.beats)) return '"beats" must be a list.';
	if (parsed.beats.some((beat) => beat === null || typeof beat !== 'object' || Array.isArray(beat))) return 'Each beat must be an object.';
	if (parsed.start !== undefined && (parsed.start === null || typeof parsed.start !== 'object')) return '"start" must be an object.';
	if (parsed.rubric !== undefined && (parsed.rubric === null || typeof parsed.rubric !== 'object')) return '"rubric" must be an object.';
	return null;
}

function Requirements({ requires, onChange, options, errorsAt }) {
	const update = (index, value) => onChange(requires.map((entry, position) => (position === index ? value : entry)));

	return (
		<div className="space-y-2">
			{requires.map((requirement, index) => {
				const kind = requirement.campaign !== undefined ? 'campaign' : 'quest';
				const source = kind === 'quest' ? options.quests.find((quest) => quest.key === requirement.quest) : options.campaigns.find((campaign) => campaign.key === requirement.campaign);
				return (
					<div key={index} className="flex flex-wrap items-center gap-2 hud-enter-fade">
						<select value={`${kind}:${requirement[kind] ?? ''}`} onChange={(event) => { const [nextKind, key] = event.target.value.split(':'); update(index, { [nextKind]: key, outcome: 'completed' }); }} className={`${FIELD_INPUT} w-auto`} aria-label="Required quest or campaign">
							<optgroup label="Quests">{options.quests.map((quest) => <option key={quest.key} value={`quest:${quest.key}`}>{quest.title}</option>)}</optgroup>
							{options.campaigns.length > 0 && <optgroup label="Campaigns">{options.campaigns.map((campaign) => <option key={campaign.key} value={`campaign:${campaign.key}`}>{campaign.title}</option>)}</optgroup>}
						</select>
						<select value={requirement.outcome} onChange={(event) => update(index, { ...requirement, outcome: event.target.value })} className={`${FIELD_INPUT} w-auto`} aria-label="Outcome">
							{OUTCOMES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
							{(source?.tiers ?? []).map((tier) => <option key={tier} value={`tier:${tier}`}>Tier: {tier}</option>)}
						</select>
						<button type="button" onClick={() => onChange(requires.filter((_, position) => position !== index))} className="text-fg-3 hover:text-danger text-xs px-1 cursor-pointer transition-colors" aria-label="Remove requirement">✕</button>
						<FieldErrors messages={errorsAt(`requires.${index}`, true)} />
					</div>
				);
			})}
			{options.quests.length > 0 && (
				<button type="button" onClick={() => onChange([...requires, { quest: options.quests[0].key, outcome: 'completed' }])} className={SMALL_BUTTON}>+ QUEST OUTCOME</button>
			)}
		</div>
	);
}

const REWARD_SOURCES = [['none', 'No reward'], ['resident', 'From a resident'], ['object', 'From an object']];

function QuestRewardEditor({ value, onChange, options, errorsAt }) {
	const source = value?.from?.resident !== undefined ? 'resident' : value?.from?.object !== undefined ? 'object' : 'none';
	const regionsWithObjects = options.regions.filter((region) => region.objects.length > 0);
	const objectRegion = regionsWithObjects.find((region) => region.id === value?.from?.object?.region) ?? null;
	const choose = (next) => {
		if (next === 'none') return onChange(null);
		const prose = value?.prose ?? '';
		if (next === 'resident') return onChange({ from: { resident: options.residents[0]?.id ?? null }, prose });
		const region = regionsWithObjects[0];
		return onChange({ from: { object: { region: region?.id ?? null, object: region?.objects[0]?.id ?? null } }, prose });
	};
	const chooseRegion = (regionId) => {
		const region = regionsWithObjects.find((candidate) => candidate.id === regionId);
		onChange({ ...value, from: { object: { region: regionId, object: region?.objects[0]?.id ?? null } } });
	};

	return (
		<div className="space-y-2">
			<div className="flex flex-wrap items-center gap-1">
				{REWARD_SOURCES.map(([key, label]) => (
					<button key={key} type="button" onClick={() => choose(key)} className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border cursor-pointer transition-colors ${source === key ? 'border-accent text-accent bg-accent/10' : 'border-line-1 text-fg-3 hover:border-fg-3'}`}>
						{label}
					</button>
				))}
				{source === 'resident' && (
					<select value={value.from.resident ?? ''} onChange={(event) => onChange({ ...value, from: { resident: Number(event.target.value) } })} className={`${FIELD_INPUT} w-auto ml-2`} aria-label="Reward giver">
						{options.residents.map((resident) => <option key={resident.id} value={resident.id}>{resident.name}</option>)}
					</select>
				)}
				{source === 'object' && (
					<>
						<select value={value.from.object.region ?? ''} onChange={(event) => chooseRegion(Number(event.target.value))} className={`${FIELD_INPUT} w-auto ml-2`} aria-label="Reward region">
							{regionsWithObjects.map((region) => <option key={region.id} value={region.id}>{region.name}</option>)}
						</select>
						<select value={value.from.object.object ?? ''} onChange={(event) => onChange({ ...value, from: { object: { ...value.from.object, object: event.target.value } } })} className={`${FIELD_INPUT} w-auto`} aria-label="Reward object">
							{(objectRegion?.objects ?? []).map((object) => <option key={object.id} value={object.id}>{object.name}</option>)}
						</select>
					</>
				)}
			</div>
			{source !== 'none' && (
				<textarea
					value={value.prose ?? ''}
					onChange={(event) => onChange({ ...value, prose: event.target.value })}
					rows={3}
					placeholder="Once the quest is complete, give a reward based on the user's score: a spray can for a low score, a Compliance Unit plating for a high one. Thank them accordingly."
					className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`}
				/>
			)}
			<FieldErrors messages={errorsAt('reward', true)} />
		</div>
	);
}

function OptionalCondition({ label, emptyLabel, value, onChange, options, context, errors }) {
	const [editing, setEditing] = useState(value !== null);
	const custom = editing || value !== null;

	return (
		<div>
			<p className={FIELD_LABEL}>{label}</p>
			<div className="flex gap-1 mb-2">
				{[[false, emptyLabel], [true, 'A condition']].map(([isCustom, text]) => (
					<button key={text} type="button" onClick={() => { setEditing(isCustom); if (!isCustom) onChange(null); }} className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border cursor-pointer transition-colors ${custom === isCustom ? 'border-accent text-accent bg-accent/10' : 'border-line-1 text-fg-3 hover:border-fg-3'}`}>
						{text}
					</button>
				))}
			</div>
			{custom && <ConditionBuilder value={value} onChange={onChange} options={options} context={context} />}
			<FieldErrors messages={errors} />
		</div>
	);
}

/**
 * Writes one quest, in a form or as raw JSON. The server checks the
 * definition against the world and answers with problems by path.
 */
export default function QuestEditor({ worldId, quest, options, campaigns = [], onSaved, onCancel, addToast }) {
	const [draft, setDraft] = useState(() => toDraft(quest));
	const [previous, setPrevious] = useState(quest);
	const [mode, setMode] = useState('form');
	const [jsonText, setJsonText] = useState('');
	const [jsonError, setJsonError] = useState(null);
	const [errors, setErrors] = useState({});
	const [warnings, setWarnings] = useState([]);
	const [isSaving, setIsSaving] = useState(false);

	if (previous !== quest) {
		setPrevious(quest);
		setDraft(toDraft(quest));
		setErrors({});
	}

	const definition = draft.definition;
	const setDefinition = (field, value) => setDraft((current) => ({ ...current, definition: { ...current.definition, [field]: value } }));
	const errorsAt = (path, nested = false) => Object.entries(errors)
		.filter(([key]) => key === `definition.${path}` || (nested && key.startsWith(`definition.${path}.`)))
		.flatMap(([, messages]) => messages);
	const unplaced = Object.entries(errors)
		.filter(([key]) => key.startsWith('definition') && !FIELD_PATH.test(key.replace(/^definition\.?/, '')))
		.flatMap(([, messages]) => messages);

	const beats = definition.beats ?? [];
	const context = {
		questKey: draft.key,
		flags: [...new Set(beats.flatMap((beat) => (beat.grants ?? []).map((grant) => grant.flag)).filter(Boolean))],
		questions: beats.flatMap((beat) => beat.questions ?? []).filter((question) => question.id),
		beats: beats.filter((beat) => beat.id),
		types: ALL_CONDITION_TYPES,
	};

	const switchMode = (next) => {
		if (next === mode) return;
		if (next === 'json') {
			setJsonText(JSON.stringify(definition, null, 2));
			setJsonError(null);
			setMode('json');
			return;
		}
		try {
			const parsed = JSON.parse(jsonText);
			const problem = shapeProblem(parsed);
			if (problem) return setJsonError(problem);
			setDraft((current) => ({ ...current, definition: { ...NEW_DEFINITION, ...parsed } }));
			setMode('form');
		} catch (error) {
			setJsonError(`Not valid JSON: ${error.message}`);
		}
	};

	const save = async () => {
		let saving = definition;
		if (mode === 'json') {
			try {
				saving = JSON.parse(jsonText);
			} catch (error) {
				return setJsonError(`Not valid JSON: ${error.message}`);
			}
		}
		setIsSaving(true);
		try {
			const payload = { key: draft.key.trim(), title: draft.title.trim(), campaignId: draft.campaignId, definition: saving };
			const response = quest
				? await api.patch(route('worlds.quests.update', { world: worldId, quest: quest.id }), payload)
				: await api.post(route('worlds.quests.store', { world: worldId }), payload);
			const body = await response.json();
			if (response.status === 422) {
				setErrors(body.errors ?? {});
				throw new Error('The quest has problems; they are shown on the fields.');
			}
			if (!response.ok) throw new Error(body.message);
			setErrors({});
			setWarnings(body.warnings ?? []);
			addToast(`${body.quest.title} saved`, 'success');
			await onSaved(body.quest);
		} catch (error) { addToast(error.message || 'Failed to save the quest', 'error'); } finally { setIsSaving(false); }
	};

	const canSave = draft.key.trim() !== '' && draft.title.trim() !== '' && !isSaving;

	return (
		<div className="space-y-4">
			<div className="flex items-start gap-3">
				<div className="flex-1 grid grid-cols-1 md:grid-cols-[1fr_16rem] gap-3">
					<div>
						<label className={FIELD_LABEL}>Title</label>
						<input value={draft.title} onChange={(event) => setDraft((current) => ({ ...current, title: event.target.value }))} placeholder="The Flooded Mill" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} />
						<FieldErrors messages={errors.title} />
					</div>
					<div>
						<label className={FIELD_LABEL}>Key <span className={HINT}>(what other quests name)</span></label>
						<input value={draft.key} onChange={(event) => setDraft((current) => ({ ...current, key: event.target.value }))} placeholder="the-flooded-mill" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} />
						<FieldErrors messages={errors.key} />
					</div>
				</div>
				<div className="flex gap-1 pt-5">
					{['form', 'json'].map((option) => (
						<button key={option} type="button" onClick={() => switchMode(option)} className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border transition-colors cursor-pointer ${mode === option ? 'border-accent text-accent bg-accent/10' : 'border-line-1 text-fg-3 hover:border-fg-3'}`}>
							{option === 'form' ? 'Form' : 'JSON'}
						</button>
					))}
				</div>
			</div>

			{campaigns.length > 0 && (
				<div className="max-w-sm">
					<label className={FIELD_LABEL}>Campaign</label>
					<select value={draft.campaignId ?? ''} onChange={(event) => setDraft((current) => ({ ...current, campaignId: event.target.value === '' ? null : Number(event.target.value) }))} className={FIELD_INPUT}>
						<option value="">— none —</option>
						{campaigns.map((campaign) => <option key={campaign.id} value={campaign.id}>{campaign.title}</option>)}
					</select>
					<FieldErrors messages={errors.campaignId} />
				</div>
			)}

			{unplaced.length > 0 && (
				<div className="border border-danger/40 bg-danger/5 px-3 py-2 space-y-0.5 hud-enter-fade">
					{unplaced.map((message) => <p key={message} className="text-danger text-xs">{message}</p>)}
				</div>
			)}

			{mode === 'json' ? (
				<div>
					<textarea value={jsonText} onChange={(event) => { setJsonText(event.target.value); setJsonError(null); }} rows={24} spellCheck={false} className={`${FIELD_INPUT} resize-y font-mono text-xs`} aria-label="Definition as JSON" />
					{jsonError && <p className="text-danger text-[0.65rem] tracking-[0.05em] mt-1">{jsonError}</p>}
					{Object.keys(errors).length > 0 && (
						<div className="mt-2 space-y-0.5">
							{Object.entries(errors).filter(([key]) => key.startsWith('definition.')).map(([key, messages]) => <p key={key} className="text-danger text-[0.65rem]"><span className="text-fg-3">{key.replace(/^definition\./, '')}</span> — {messages.join(' ')}</p>)}
						</div>
					)}
				</div>
			) : (
				<div className="space-y-4">
					<div>
						<label className={FIELD_LABEL}>Description</label>
						<textarea value={definition.description} onChange={(event) => setDefinition('description', event.target.value)} rows={2} placeholder="The mill flooded, and the village blames the miller." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} />
						<FieldErrors messages={errorsAt('description')} />
					</div>

					<div>
						<p className={FIELD_LABEL}>Starts</p>
						<div className="flex flex-wrap items-center gap-1">
							{START_MODES.map(([value, label]) => (
								<button key={value} type="button" onClick={() => setDefinition('start', value === 'offer' ? { mode: 'offer', giver: options.residents[0]?.id ?? null } : value === 'condition' ? { mode: 'condition', when: null } : { mode: 'auto' })} className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border cursor-pointer transition-colors ${definition.start?.mode === value ? 'border-accent text-accent bg-accent/10' : 'border-line-1 text-fg-3 hover:border-fg-3'}`}>
									{label}
								</button>
							))}
							{definition.start?.mode === 'offer' && (
								<select value={definition.start.giver ?? ''} onChange={(event) => setDefinition('start', { mode: 'offer', giver: Number(event.target.value) })} className={`${FIELD_INPUT} w-auto ml-2`} aria-label="Giver">
									{options.residents.map((resident) => <option key={resident.id} value={resident.id}>{resident.name}</option>)}
								</select>
							)}
						</div>
						{definition.start?.mode === 'condition' && (
							<div className="mt-2"><ConditionBuilder value={definition.start.when ?? null} onChange={(value) => setDefinition('start', { mode: 'condition', when: value })} options={options} context={{ ...context, types: ALL_CONDITION_TYPES.filter((type) => !['beat', 'question'].includes(type)) }} /></div>
						)}
						<FieldErrors messages={errorsAt('start', true)} />
					</div>

					<div className="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-start">
						<div>
							<p className={FIELD_LABEL}>Requires</p>
							<Requirements requires={definition.requires ?? []} onChange={(value) => setDefinition('requires', value)} options={{ ...options, quests: options.quests.filter((candidate) => candidate.key !== quest?.key) }} errorsAt={errorsAt} />
						</div>
						<label className="flex items-center gap-3 text-fg-2 text-sm pt-5">
							<Toggle checked={definition.repeatable ?? false} onChange={() => setDefinition('repeatable', !(definition.repeatable ?? false))} />
							<span>Can be played again</span>
						</label>
					</div>

					<p className={SECTION}><span>BEATS</span><span className="h-px flex-1 bg-line-1" /></p>
					<div className="space-y-3">
						{beats.map((beat, index) => (
							<QuestBeatEditor
								key={index}
								beat={beat}
								index={index}
								beats={beats}
								onChange={(value) => setDefinition('beats', beats.map((current, position) => (position === index ? value : current)))}
								onRemove={() => setDefinition('beats', beats.filter((_, position) => position !== index))}
								options={options}
								context={context}
								errorsAt={errorsAt}
							/>
						))}
						<button type="button" onClick={() => setDefinition('beats', [...beats, newBeat()])} className="w-full border border-dashed border-line-1 px-3 py-2 text-[0.7rem] tracking-[0.1em] text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer">+ BEAT</button>
					</div>

					<div className="grid grid-cols-1 gap-4">
						<OptionalCondition label="Completes when" emptyLabel="Every beat is finished" value={definition.complete ?? null} onChange={(value) => setDefinition('complete', value)} options={options} context={context} errors={errorsAt('complete', true)} />
						<OptionalCondition label="Fails when" emptyLabel="Never" value={definition.fail ?? null} onChange={(value) => setDefinition('fail', value)} options={options} context={context} errors={errorsAt('fail', true)} />
					</div>

					<p className={SECTION}><span>ENDING</span><span className="h-px flex-1 bg-line-1" /></p>
					<RubricEditor value={definition.rubric} onChange={(value) => setDefinition('rubric', value)} errorsAt={errorsAt} />

					<p className={SECTION}><span>REWARD</span><span className="h-px flex-1 bg-line-1" /></p>
					<QuestRewardEditor value={definition.reward ?? null} onChange={(value) => setDefinition('reward', value)} options={options} errorsAt={errorsAt} />
				</div>
			)}

			{warnings.length > 0 && (
				<div className="border border-warning/40 bg-warning/5 px-3 py-2 space-y-0.5">
					{warnings.map((warning) => <p key={warning} className="text-warning text-xs">{warning}</p>)}
				</div>
			)}

			<div className="flex justify-end gap-3">
				{onCancel && <button type="button" onClick={onCancel} className="text-fg-3 text-[0.7rem] tracking-[0.1em] px-4 py-1.5 cursor-pointer hover:text-fg-1 transition-colors">CANCEL</button>}
				<button type="button" onClick={save} disabled={!canSave} className={`text-[0.7rem] tracking-[0.1em] px-4 py-1.5 transition-colors ${canSave ? 'button-success cursor-pointer' : 'bg-bg-3 text-fg-3 cursor-default'}`}>
					{isSaving ? 'SAVING...' : quest ? 'SAVE QUEST' : 'CREATE QUEST'}
				</button>
			</div>
		</div>
	);
}
