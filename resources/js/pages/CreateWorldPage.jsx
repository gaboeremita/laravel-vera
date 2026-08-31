import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import WorldForm from '../components/WorldForm.jsx';

export default function CreateWorldPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const [value, setValue] = useState({ name: '', slug: '', description: '', assistantContextPrompt: '', npcContextPrompt: '' });
	const [environment, setEnvironment] = useState(null);
	const [isSaving, setIsSaving] = useState(false);

	const save = async () => {
		if (!environment) return addToast('A GLB environment is required', 'error');
		setIsSaving(true);
		try {
			const form = new FormData();
			form.append('name', value.name); form.append('slug', value.slug); form.append('description', value.description);
			form.append('assistant_context_prompt', value.assistantContextPrompt); form.append('npc_context_prompt', value.npcContextPrompt); form.append('environment', environment);
			const response = await api.postForm(route('worlds.store'), form);
			if (!response.ok) throw new Error((await response.json()).message);
			const world = await response.json();
			navigate(`/worlds/${world.id}/edit`);
		} catch (error) { addToast(error.message || 'Failed to create world', 'error'); } finally { setIsSaving(false); }
	};

	return <><Header hideSettings onBack={() => navigate('/worlds')}><span className="text-fg-2 text-lg tracking-[0.05em]">Create World</span></Header><WorldForm value={value} onChange={setValue} environmentFile={environment} onEnvironmentChange={setEnvironment} isSaving={isSaving} submitLabel={save} /></>;
}
