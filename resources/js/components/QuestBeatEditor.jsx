import { useState } from 'react';
import ConditionBuilder from './ConditionBuilder.jsx';
import { ALL_CONDITION_TYPES } from '../utils/questConditionTypes.js';
import Toggle from './common/Toggle.jsx';
import { FieldErrors } from './RubricEditor.jsx';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const HINT = 'normal-case text-fg-3';
const SMALL_BUTTON = 'text-[0.65rem] tracking-[0.1em] px-2 py-1 border border-dashed border-line-1 text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer';
const REMOVE = 'text-fg-3 hover:text-danger text-xs px-1 py-2 cursor-pointer transition-colors';

function ResidentOptions({ residents }) {
	return residents.map((resident) => <option key={resident.id} value={resident.id}>{resident.name}{resident.toolsUnsupported ? ' (no tool calling)' : ''}</option>);
}

function Section({ title, hint, count, children }) {
	const [open, setOpen] = useState(count > 0);
	return (
		<div className="border border-line-1">
			<button type="button" onClick={() => setOpen((current) => !current)} className="w-full flex items-center gap-2 px-3 py-2 text-left cursor-pointer hover:bg-bg-1 transition-colors">
				<span className={`text-fg-3 text-[0.6rem] transition-transform ${open ? 'rotate-90' : ''}`}>▶</span>
				<span className="text-fg-2 text-[0.65rem] tracking-[0.15em] uppercase">{title}</span>
				{count > 0 && <span className="text-accent text-[0.6rem] border border-accent/40 px-1.5">{count}</span>}
				<span className="text-fg-3 text-xs normal-case truncate">{hint}</span>
			</button>
			{open && <div className="px-3 pb-3 space-y-2">{children}</div>}
		</div>
	);
}

/** One beat of a quest: its text, what it follows, what finishes it, and what residents know, grant and judge while it is current. */
export default function QuestBeatEditor({ beat, index, beats, onChange, onRemove, options, context, errorsAt }) {
	const update = (field, value) => onChange({ ...beat, [field]: value });
	const updateAt = (field, position, value) => update(field, beat[field].map((entry, current) => (current === position ? value : entry)));
	const removeAt = (field, position) => update(field, beat[field].filter((_, current) => current !== position));
	const path = `beats.${index}`;
	const others = beats.filter((other) => other.id !== beat.id);
	const firstResident = options.residents[0]?.id ?? null;

	return (
		<div className="border border-line-1 bg-bg-1/40 p-4 space-y-3 hud-enter-fade">
			<div className="flex items-center gap-3">
				<span className="text-accent text-[0.65rem] tracking-[0.15em]">{index + 1}</span>
				<input value={beat.id} onChange={(event) => update('id', event.target.value)} placeholder="find-the-ledger" className="bg-transparent border-b border-line-1 text-fg-1 text-sm px-1 py-0.5 outline-none focus:border-accent/50 w-48 placeholder:text-fg-3/60" aria-label="Beat id" />
				<label className="flex items-center gap-2 text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase ml-auto">
					<Toggle checked={beat.hidden ?? false} onChange={() => update('hidden', !(beat.hidden ?? false))} />
					Hidden until finished
				</label>
				<button type="button" onClick={onRemove} className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer">REMOVE</button>
			</div>
			<FieldErrors messages={errorsAt(`${path}.id`)} />

			<div>
				<label className={FIELD_LABEL}>Player text</label>
				<input value={beat.text} onChange={(event) => update('text', event.target.value)} placeholder="Find the ledger the harbourmaster lost." className={`${FIELD_INPUT} placeholder:text-fg-3/60`} />
				<FieldErrors messages={errorsAt(`${path}.text`)} />
			</div>

			<div>
				<p className={FIELD_LABEL}>After <span className={HINT}>(beats that must be finished first)</span></p>
				<div className="flex flex-wrap gap-1.5">
					{others.length === 0 && <span className="text-fg-3 text-xs italic">— none —</span>}
					{others.map((other) => {
						const selected = (beat.requires ?? []).includes(other.id);
						return (
							<button key={other.id} type="button" onClick={() => update('requires', selected ? beat.requires.filter((id) => id !== other.id) : [...(beat.requires ?? []), other.id])} className={`text-xs px-2 py-1 border cursor-pointer transition-colors ${selected ? 'border-accent text-accent bg-accent/10' : 'border-line-1 text-fg-3 hover:border-fg-3'}`}>
								{other.id || '(unnamed)'}
							</button>
						);
					})}
				</div>
				<FieldErrors messages={errorsAt(`${path}.requires`, true)} />
			</div>

			<div>
				<p className={FIELD_LABEL}>Finishes when</p>
				<ConditionBuilder value={beat.when ?? null} onChange={(value) => update('when', value)} options={options} context={{ ...context, types: ALL_CONDITION_TYPES.filter((type) => type !== 'beat') }} />
				<FieldErrors messages={errorsAt(`${path}.when`, true)} />
			</div>

			<Section title="What residents know" hint="— prose a resident is given while this beat is current" count={beat.knowledge?.length ?? 0}>
				{(beat.knowledge ?? []).map((entry, position) => (
					<div key={position} className="grid grid-cols-[12rem_1fr_auto] gap-2 items-start">
						<select value={entry.resident ?? ''} onChange={(event) => updateAt('knowledge', position, { ...entry, resident: Number(event.target.value) })} className={FIELD_INPUT} aria-label="Resident"><ResidentOptions residents={options.residents} /></select>
						<textarea value={entry.prose} onChange={(event) => updateAt('knowledge', position, { ...entry, prose: event.target.value })} rows={2} placeholder="You are ashamed and defensive; you want someone to believe you." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} />
						<button type="button" onClick={() => removeAt('knowledge', position)} className={REMOVE} aria-label="Remove">✕</button>
					</div>
				))}
				<button type="button" onClick={() => update('knowledge', [...(beat.knowledge ?? []), { resident: firstResident, prose: '' }])} className={SMALL_BUTTON}>+ RESIDENT</button>
				<FieldErrors messages={errorsAt(`${path}.knowledge`, true)} />
			</Section>

			<Section title="Flags residents grant" hint="— a resident decides in character when the player has earned it" count={beat.grants?.length ?? 0}>
				{(beat.grants ?? []).map((entry, position) => (
					<div key={position} className="grid grid-cols-[12rem_1fr_auto] gap-2 items-start">
						<select value={entry.resident ?? ''} onChange={(event) => updateAt('grants', position, { ...entry, resident: Number(event.target.value) })} className={FIELD_INPUT} aria-label="Resident"><ResidentOptions residents={options.residents} /></select>
						<input value={entry.flag} onChange={(event) => updateAt('grants', position, { ...entry, flag: event.target.value })} placeholder="elderConvinced" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} aria-label="Flag" />
						<button type="button" onClick={() => removeAt('grants', position)} className={REMOVE} aria-label="Remove">✕</button>
					</div>
				))}
				<button type="button" onClick={() => update('grants', [...(beat.grants ?? []), { resident: firstResident, flag: '' }])} className={SMALL_BUTTON}>+ FLAG</button>
				<FieldErrors messages={errorsAt(`${path}.grants`, true)} />
			</Section>

			<Section title="Questions" hint="— a named resident signals when they believe it's met, and a separate model checks" count={beat.questions?.length ?? 0}>
				{(beat.questions ?? []).map((question, position) => (
					<div key={position} className="border-l border-line-1 pl-3 space-y-2">
						<div className="grid grid-cols-[10rem_1fr_auto] gap-2 items-start">
							<input value={question.id} onChange={(event) => updateAt('questions', position, { ...question, id: event.target.value })} placeholder="cleared" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} aria-label="Question id" />
							<input value={question.text} onChange={(event) => updateAt('questions', position, { ...question, text: event.target.value })} placeholder="Has the player convinced the elder that the flood wasn't the miller's fault?" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} aria-label="Question" />
							<button type="button" onClick={() => removeAt('questions', position)} className={REMOVE} aria-label="Remove">✕</button>
						</div>
						<div className="flex flex-wrap gap-1.5">
							{options.residents.map((resident) => {
								const selected = (question.residents ?? []).includes(resident.id);
								return (
									<button key={resident.id} type="button" onClick={() => updateAt('questions', position, { ...question, residents: selected ? question.residents.filter((id) => id !== resident.id) : [...(question.residents ?? []), resident.id] })} className={`text-xs px-2 py-1 border cursor-pointer transition-colors ${selected ? 'border-accent text-accent bg-accent/10' : 'border-line-1 text-fg-3 hover:border-fg-3'}`}>
										{resident.name}
									</button>
								);
							})}
						</div>
					</div>
				))}
				<button type="button" onClick={() => update('questions', [...(beat.questions ?? []), { id: '', text: '', residents: [] }])} className={SMALL_BUTTON}>+ QUESTION</button>
				<FieldErrors messages={errorsAt(`${path}.questions`, true)} />
			</Section>
		</div>
	);
}
