import { useState } from 'react';
import Accordion from './common/Accordion.jsx';

const FIELD_LABEL = 'text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1';
const FIELD_INPUT = 'w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors';

export default function WorldForm({ value, onChange, environmentFile, onEnvironmentChange, isSaving, submitLabel, children }) {
	const update = (field, fieldValue) => onChange({ ...value, [field]: fieldValue });
	const [sections, setSections] = useState({ details: false, environment: false, context: false });
	const toggle = (section) => setSections((current) => ({ ...current, [section]: !current[section] }));

	return (
		<form onSubmit={(event) => { event.preventDefault(); submitLabel(); }} className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-5">
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
			</Accordion>
			<Accordion label="ENVIRONMENT" collapsed={sections.environment} onToggle={() => toggle('environment')}>
				<div>
					<label className={FIELD_LABEL}>Room Environment (.glb)</label>
					<input
						type="file"
						accept=".glb,model/gltf-binary"
						onChange={(event) => onEnvironmentChange(event.target.files?.[0] ?? null)}
						className="w-full text-sm text-accent file:mr-3 file:border file:border-line-1 file:bg-bg-1 file:text-fg-3 file:text-[0.65rem] file:tracking-[0.1em] file:px-3 file:py-1 file:cursor-pointer"
						required={!environmentFile && !value.environmentUrl}
					/>
					{environmentFile && <span className="text-fg-3 text-[0.65rem] mt-1 block">{environmentFile.name}</span>}
					{!environmentFile && value.environmentUrl && <a href={value.environmentUrl} className="text-info text-xs hover:text-fg-1 mt-1 block" target="_blank" rel="noreferrer">CURRENT ENVIRONMENT</a>}
				</div>
			</Accordion>
			<Accordion label="CONVERSATION CONTEXT" collapsed={sections.context} onToggle={() => toggle('context')}>
				<div>
					<label className={FIELD_LABEL}>Companion Assistant World Context</label>
					<textarea value={value.assistantContextPrompt} onChange={(event) => update('assistantContextPrompt', event.target.value)} rows={4} className={`${FIELD_INPUT} resize-none`} required />
				</div>
				<div>
					<label className={FIELD_LABEL}>NPC World Context</label>
					<textarea value={value.npcContextPrompt} onChange={(event) => update('npcContextPrompt', event.target.value)} rows={4} className={`${FIELD_INPUT} resize-none`} required />
				</div>
			</Accordion>
			{children}
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
