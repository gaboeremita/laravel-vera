import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import WorldForm from '../components/WorldForm.jsx';
import RegionsTab from '../components/RegionsTab.jsx';
import WorldImagesEditor from '../components/WorldImagesEditor.jsx';
import ConfirmationModal from '../components/common/ConfirmationModal.jsx';

const TABS = [{ id: 'world', label: 'WORLD' }, { id: 'regions', label: 'REGIONS' }];

function toValue(world) {
	return { ...world, spawnRegionId: world.spawnRegionId ?? null, spawnPassageId: world.spawnPassageId ?? null, regions: world.regions ?? [], residents: world.residents ?? [] };
}

export default function EditWorldPage() {
	const { worldId } = useParams();
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const [value, setValue] = useState(null);
	const [tab, setTab] = useState('world');
	const [isSaving, setIsSaving] = useState(false);
	const [confirmingDelete, setConfirmingDelete] = useState(false);

	const fetchWorld = useCallback(async () => {
		const response = await api.get(route('worlds.show', { world: worldId }));
		return response.ok ? toValue(await response.json()) : null;
	}, [worldId]);

	useEffect(() => {
		let active = true;
		const load = async () => {
			const world = await fetchWorld();
			if (!active) return;
			if (world === null) return navigate('/worlds');
			setValue(world);
		};
		void load();
		return () => { active = false; };
	}, [fetchWorld, navigate]);

	const reloadWorld = useCallback(async () => {
		const world = await fetchWorld();
		if (world !== null) setValue(world);
	}, [fetchWorld]);

	const setResidents = useCallback((update) => setValue((current) => ({ ...current, residents: update(current.residents) })), []);

	const save = async () => {
		setIsSaving(true);
		try {
			const response = await api.patch(route('worlds.update', { world: worldId }), {
				name: value.name, slug: value.slug, description: value.description,
				assistantContextPrompt: value.assistantContextPrompt, npcContextPrompt: value.npcContextPrompt,
				spawnRegionId: value.spawnRegionId, spawnPassageId: value.spawnPassageId,
			});
			const saved = await response.json();
			if (!response.ok) throw new Error(saved.message);
			setValue(toValue(saved));
			addToast('World saved', 'success');
		} catch (error) { addToast(error.message || 'Failed to save world', 'error'); } finally { setIsSaving(false); }
	};

	const handleDelete = async () => {
		setConfirmingDelete(false);
		try {
			const response = await api.delete(route('worlds.destroy', { world: worldId }));
			if (!response.ok) throw new Error();
			addToast('World deleted', 'success');
			navigate('/worlds');
		} catch { addToast('Failed to delete world', 'error'); }
	};

	if (!value) return null;

	return (
		<>
			<Header hideSettings onBack={() => navigate('/worlds')} actions={<button type="button" onClick={() => setConfirmingDelete(true)} className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger transition-colors">DELETE</button>}>
				<span className="text-fg-2 text-lg tracking-[0.05em]">Edit World</span>
			</Header>
			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-5">
				<div className="flex gap-2 border-b border-line-1">
					{TABS.map((item) => (
						<button key={item.id} type="button" onClick={() => setTab(item.id)} className={`px-4 py-2 text-[0.7rem] tracking-[0.1em] cursor-pointer transition-colors border-b-2 -mb-px ${tab === item.id ? 'border-accent text-accent' : 'border-transparent text-fg-3 hover:text-fg-1'}`}>
							{item.label}
						</button>
					))}
				</div>
				{tab === 'world' ? (
					<WorldForm
						value={value}
						onChange={setValue}
						regions={value.regions}
						imagesEditor={<WorldImagesEditor routePrefix="worlds.image" routeParams={{ world: value.id }} cardImageUrl={value.cardImageUrl} portraitImageUrl={value.portraitImageUrl} addToast={addToast} />}
						isSaving={isSaving}
						onSubmit={save}
					/>
				) : (
					<RegionsTab world={value} onWorldReload={reloadWorld} onResidentsChange={setResidents} addToast={addToast} />
				)}
			</div>
			{confirmingDelete && <ConfirmationModal title="Delete world" message={`Delete "${value.name}"? Its regions, environment assets, resident placements and sessions will be removed. Assistants and NPCs will not be affected.`} options={[{ label: 'DELETE', value: 'confirm', destructive: true }, { label: 'CANCEL', value: 'cancel', cancel: true }]} onSelect={(selected) => { if (selected === 'confirm') handleDelete(); else setConfirmingDelete(false); }} />}
		</>
	);
}
