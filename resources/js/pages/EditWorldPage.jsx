import { useEffect, useState } from 'react';
import { useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import WorldForm from '../components/WorldForm.jsx';
import WorldResidentsEditor from '../components/WorldResidentsEditor.jsx';

export default function EditWorldPage() {
	const { worldId } = useParams(); const navigate = useNavigate(); const { addToast } = useOutletContext();
	const [value, setValue] = useState(null); const [environment, setEnvironment] = useState(null); const [isSaving, setIsSaving] = useState(false);
	useEffect(() => { const load = async () => { const response = await api.get(route('worlds.show', { world: worldId })); if (!response.ok) return navigate('/worlds'); const world = await response.json(); setValue({ id: world.id, name: world.name, slug: world.slug, description: world.description, assistantContextPrompt: world.assistant_context_prompt, npcContextPrompt: world.npc_context_prompt, environmentUrl: world.environment_url, residents: world.residents ?? [] }); }; void load(); }, [navigate, worldId]);
	const save = async () => { setIsSaving(true); try { const form = new FormData(); form.append('name', value.name); form.append('slug', value.slug); form.append('description', value.description); form.append('assistant_context_prompt', value.assistantContextPrompt); form.append('npc_context_prompt', value.npcContextPrompt); if (environment) form.append('environment', environment); const response = await api.patchForm(route('worlds.update', { world: worldId }), form); if (!response.ok) throw new Error((await response.json()).message); addToast('World saved', 'success'); } catch (error) { addToast(error.message || 'Failed to save world', 'error'); } finally { setIsSaving(false); } };
	if (!value) return null;
	return <><Header hideSettings onBack={() => navigate('/worlds')}><span className="text-fg-2 text-lg tracking-[0.05em]">Edit World</span></Header><WorldForm value={value} onChange={setValue} environmentFile={environment} onEnvironmentChange={setEnvironment} isSaving={isSaving} submitLabel={save} />{value.id && <div className="px-5 pb-5"><WorldResidentsEditor world={value} onWorldChange={setValue} addToast={addToast} /></div>}</>;
}
