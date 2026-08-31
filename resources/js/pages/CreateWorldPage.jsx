import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import WorldForm from '../components/WorldForm.jsx';
import WorldResidentsEditor from '../components/WorldResidentsEditor.jsx';

export default function CreateWorldPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const [value, setValue] = useState({ name: '', slug: '', description: '', assistantContextPrompt: '', npcContextPrompt: '', residents: [] });
	const [environment, setEnvironment] = useState(null);
	const [isSaving, setIsSaving] = useState(false);

	const save = async () => {
		if (!environment) return addToast('A GLB environment is required', 'error');
		setIsSaving(true);
		try {
			const form = new FormData();
			form.append('name', value.name); form.append('slug', value.slug); form.append('description', value.description);
			form.append('assistantContextPrompt', value.assistantContextPrompt); form.append('npcContextPrompt', value.npcContextPrompt); form.append('environment', environment);
			const response = await api.postForm(route('worlds.store'), form);
			if (!response.ok) throw new Error((await response.json()).message);
			const world = await response.json();

			const failures = [];
			for (const resident of value.residents) {
				const residentResponse = await api.put(route('worlds.residents.upsert', { world: world.id, assistant: resident.assistant.id }), { position: resident.position, behavior: resident.behavior, openingMessage: resident.openingMessage, customPrompt: resident.customPrompt });
				if (!residentResponse.ok) failures.push(resident.assistant.name);
			}
			if (failures.length > 0) addToast(`World saved, but ${failures.join(', ')} failed to attach — add them on the edit screen`, 'error');

			navigate(`/worlds/${world.id}/edit`);
		} catch (error) { addToast(error.message || 'Failed to create world', 'error'); } finally { setIsSaving(false); }
	};

	return <><Header hideSettings onBack={() => navigate('/worlds')}><span className="text-fg-2 text-lg tracking-[0.05em]">Create World</span></Header><WorldForm value={value} onChange={setValue} environmentFile={environment} onEnvironmentChange={setEnvironment} isSaving={isSaving} submitLabel={save}><WorldResidentsEditor world={value} onWorldChange={setValue} addToast={addToast} /></WorldForm></>;
}
