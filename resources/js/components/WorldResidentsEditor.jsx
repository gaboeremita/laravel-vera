import { useEffect, useMemo, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Accordion from './common/Accordion.jsx';

const DEFAULT_PLACEMENT = { position: { x: 0, y: 0, z: 0 }, behavior: 'stationary' };

export default function WorldResidentsEditor({ world, onWorldChange, addToast }) {
	const [candidates, setCandidates] = useState([]);
	const [collapsed, setCollapsed] = useState(false);
	const [isLoading, setIsLoading] = useState(true);
	const residentIds = useMemo(() => new Set(world.residents.map((resident) => resident.assistant.id)), [world.residents]);

	useEffect(() => {
		const load = async () => {
			try {
				const [assistantsResponse, npcsResponse] = await Promise.all([api.get(route('assistants.index')), api.get(route('npcs.index'))]);
				if (!assistantsResponse.ok || !npcsResponse.ok) throw new Error();
				const [assistants, npcs] = await Promise.all([assistantsResponse.json(), npcsResponse.json()]);
				setCandidates([...assistants, ...npcs].filter((candidate) => candidate.portrait_type === 'avatar3d' && candidate.vrm_url));
			} catch { addToast('Failed to load eligible residents', 'error'); } finally { setIsLoading(false); }
		};
		void load();
	}, [addToast]);

	const updateResident = async (assistant, placement) => {
		try {
			const response = await api.put(route('worlds.residents.upsert', { world: world.id, assistant: assistant.id }), placement);
			if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || 'Unable to save resident');
			const resident = await response.json();
			onWorldChange((current) => ({ ...current, residents: [...current.residents.filter((item) => item.assistant.id !== assistant.id), resident] }));
		} catch (error) { addToast(error.message || 'Unable to save resident', 'error'); }
	};

	const removeResident = async (resident) => {
		try {
			const response = await api.delete(route('worlds.residents.destroy', { world: world.id, assistant: resident.assistant.id }));
			if (!response.ok) throw new Error();
			onWorldChange((current) => ({ ...current, residents: current.residents.filter((item) => item.id !== resident.id) }));
		} catch { addToast('Unable to remove resident', 'error'); }
	};

	return <Accordion label="RESIDENTS" collapsed={collapsed} onToggle={() => setCollapsed((current) => !current)} badge={<span className="text-fg-3 text-xs">{world.residents.length}</span>}><div className="space-y-3">{world.residents.map((resident) => <div key={resident.id} className="border border-line-1 p-3"><div className="flex items-center justify-between gap-3"><span className="text-fg-1 text-sm">{resident.assistant.name}</span><button type="button" onClick={() => removeResident(resident)} className="text-danger text-[0.65rem] tracking-[0.1em]">REMOVE</button></div><div className="mt-3 grid grid-cols-3 gap-2">{['x', 'y', 'z'].map((axis) => <label key={axis} className="text-fg-3 text-[0.65rem] uppercase">{axis}<input type="number" value={resident.position[axis]} onChange={(event) => updateResident(resident.assistant, { ...resident, position: { ...resident.position, [axis]: Number(event.target.value) }, behavior: resident.behavior })} className="mt-1 w-full bg-bg-1 p-2 text-fg-1" /></label>)}</div><select value={resident.behavior} onChange={(event) => updateResident(resident.assistant, { ...resident, behavior: event.target.value })} className="mt-3 w-full bg-bg-1 p-2 text-fg-1 text-sm"><option value="stationary">Stationary</option><option value="roam">Roam</option></select></div>)}{isLoading ? <p className="text-fg-3 text-xs">Loading eligible characters...</p> : candidates.filter((candidate) => !residentIds.has(candidate.id)).map((candidate) => <div key={candidate.id} className="flex items-center justify-between border border-line-1 p-3"><div><p className="text-fg-1 text-sm">{candidate.name}</p><p className="text-fg-3 text-[0.65rem]">{candidate.kind === 'world_npc' ? 'NPC' : 'ASSISTANT'}</p></div><button type="button" onClick={() => updateResident(candidate, DEFAULT_PLACEMENT)} className="text-success text-[0.65rem] tracking-[0.1em]">ADD</button></div>)}</div></Accordion>;
}
