import { useState } from 'react';
import Accordion from './common/Accordion.jsx';

export default function WorldForm({ value, onChange, environmentFile, onEnvironmentChange, isSaving, submitLabel }) {
	const update = (field, fieldValue) => onChange({ ...value, [field]: fieldValue });
	const [sections, setSections] = useState({ details: false, environment: false, context: false });
	const toggle = (section) => setSections((current) => ({ ...current, [section]: !current[section] }));

	return (
		<form onSubmit={(event) => { event.preventDefault(); submitLabel(); }} className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-5">
			<Accordion label="DETAILS" collapsed={sections.details} onToggle={() => toggle('details')}>
				<label className="block text-fg-3 text-[0.7rem] tracking-[0.1em]">NAME<input value={value.name} onChange={(event) => update('name', event.target.value)} className="mt-2 w-full bg-bg-1 border border-line-1 p-3 text-fg-1" required /></label>
				<label className="block text-fg-3 text-[0.7rem] tracking-[0.1em]">SLUG<input value={value.slug} onChange={(event) => update('slug', event.target.value)} className="mt-2 w-full bg-bg-1 border border-line-1 p-3 text-fg-1" required /></label>
				<label className="block text-fg-3 text-[0.7rem] tracking-[0.1em]">DESCRIPTION<textarea value={value.description} onChange={(event) => update('description', event.target.value)} className="mt-2 w-full min-h-24 bg-bg-1 border border-line-1 p-3 text-fg-1" required /></label>
			</Accordion>
			<Accordion label="ENVIRONMENT" collapsed={sections.environment} onToggle={() => toggle('environment')}>
				<label className="block text-fg-3 text-[0.7rem] tracking-[0.1em]">ROOM ENVIRONMENT (.GLB)<input type="file" accept=".glb,model/gltf-binary" onChange={(event) => onEnvironmentChange(event.target.files?.[0] ?? null)} className="mt-2 block text-sm text-fg-2" required={!environmentFile && !value.environmentUrl} /></label>
				{value.environmentUrl && <a href={value.environmentUrl} className="text-info text-xs hover:text-fg-1" target="_blank" rel="noreferrer">CURRENT ENVIRONMENT</a>}
			</Accordion>
			<Accordion label="CONVERSATION CONTEXT" collapsed={sections.context} onToggle={() => toggle('context')}>
				<label className="block text-fg-3 text-[0.7rem] tracking-[0.1em]">COMPANION ASSISTANT WORLD CONTEXT<textarea value={value.assistantContextPrompt} onChange={(event) => update('assistantContextPrompt', event.target.value)} className="mt-2 w-full min-h-28 bg-bg-1 border border-line-1 p-3 text-fg-1" required /></label>
				<label className="block text-fg-3 text-[0.7rem] tracking-[0.1em]">NPC WORLD CONTEXT<textarea value={value.npcContextPrompt} onChange={(event) => update('npcContextPrompt', event.target.value)} className="mt-2 w-full min-h-28 bg-bg-1 border border-line-1 p-3 text-fg-1" required /></label>
			</Accordion>
			<button disabled={isSaving} className="button-primary text-[0.7rem]">{isSaving ? 'SAVING...' : 'SAVE WORLD'}</button>
		</form>
	);
}
