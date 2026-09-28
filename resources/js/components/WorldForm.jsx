import { useState } from 'react';
import Accordion from './common/Accordion.jsx';
import PassageSelect from './PassageSelect.jsx';
import { FIELD_LABEL, FIELD_INPUT } from '../utils/formFieldStyles.js';

export default function WorldForm({ value, onChange, regions = null, imagesEditor = null, isSaving, onSubmit }) {
	const update = (field, fieldValue) => onChange({ ...value, [field]: fieldValue });
	const [sections, setSections] = useState({ details: false, prompts: false, start: false });
	const toggle = (section) => setSections((current) => ({ ...current, [section]: !current[section] }));
	const spawn = value.spawnRegionId ? { regionId: value.spawnRegionId, passageId: value.spawnPassageId } : null;

	return (
		<form onSubmit={(event) => { event.preventDefault(); onSubmit(); }} className="space-y-5">
			<Accordion label="DETAILS" collapsed={sections.details} onToggle={() => toggle('details')}>
				<div>
					<label className={FIELD_LABEL}>Name</label>
					<input value={value.name} onChange={(event) => update('name', event.target.value)} className={FIELD_INPUT} required />
				</div>
				<div>
					<label className={FIELD_LABEL}>Slug</label>
					<input value={value.slug} onChange={(event) => update('slug', event.target.value)} className={FIELD_INPUT} required />
				</div>
				<div>
					<label className={FIELD_LABEL}>Description</label>
					<textarea value={value.description} onChange={(event) => update('description', event.target.value)} rows={3} className={`${FIELD_INPUT} resize-none`} required />
				</div>
				{imagesEditor}
			</Accordion>
			<Accordion label="WORLD PROMPTS" collapsed={sections.prompts} onToggle={() => toggle('prompts')}>
				<div>
					<label className={FIELD_LABEL}>Companion Assistant World Context</label>
					<textarea value={value.assistantContextPrompt} onChange={(event) => update('assistantContextPrompt', event.target.value)} rows={4} className={`${FIELD_INPUT} resize-none`} required />
				</div>
				<div>
					<label className={FIELD_LABEL}>NPC World Context</label>
					<textarea value={value.npcContextPrompt} onChange={(event) => update('npcContextPrompt', event.target.value)} rows={4} className={`${FIELD_INPUT} resize-none`} required />
				</div>
			</Accordion>
			{regions && (
				<Accordion label="START" collapsed={sections.start} onToggle={() => toggle('start')}>
					<div>
						<label className={FIELD_LABEL}>Spawn point</label>
						<PassageSelect regions={regions} value={spawn} onChange={(passage) => onChange({ ...value, spawnRegionId: passage?.regionId ?? null, spawnPassageId: passage?.passageId ?? null })} emptyLabel="— none —" />
						{!spawn && <p className="text-warning text-xs mt-2">⚠ Choose a spawn point before starting a session in this world.</p>}
					</div>
				</Accordion>
			)}
			<div className="flex justify-end pt-2 pb-4">
				<button
					disabled={isSaving}
					className={`text-[0.75rem] tracking-[0.1em] px-6 py-2 transition-colors ${
						isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'
					}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE WORLD'}
				</button>
			</div>
		</form>
	);
}
