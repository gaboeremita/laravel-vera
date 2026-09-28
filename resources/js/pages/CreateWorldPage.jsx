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
	const [isSaving, setIsSaving] = useState(false);

	const save = async () => {
		setIsSaving(true);
		try {
			const response = await api.post(route('worlds.store'), value);
			const world = await response.json();
			if (!response.ok) throw new Error(world.message);
			navigate(`/worlds/${world.id}/edit`);
		} catch (error) { addToast(error.message || 'Failed to create world', 'error'); } finally { setIsSaving(false); }
	};

	return (
		<>
			<Header hideSettings onBack={() => navigate('/worlds')}><span className="text-fg-2 text-lg tracking-[0.05em]">Create World</span></Header>
			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar">
				<WorldForm value={value} onChange={setValue} isSaving={isSaving} onSubmit={save} />
			</div>
		</>
	);
}
