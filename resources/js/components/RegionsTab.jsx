import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { reportLayoutWarnings } from '../utils/layoutWarnings.js';
import RegionForm from './RegionForm.jsx';
import RegionPassagesEditor from './RegionPassagesEditor.jsx';
import WorldImagesEditor from './WorldImagesEditor.jsx';
import WorldTrackEditor from './WorldTrackEditor.jsx';
import WorldResidentsEditor from './WorldResidentsEditor.jsx';
import RegionObjectsEditor from './RegionObjectsEditor.jsx';
import ConfirmationModal from './common/ConfirmationModal.jsx';

const NEW_REGION = { name: '', slug: '', description: '', assistantContextPrompt: '', npcContextPrompt: '', settings: { theme: 'default' } };

function hasWarning(region) {
	return region.warnings.noPassages || region.warnings.unlinkedPassages > 0;
}

function RegionEditor({ world, inventoryConfig, regionId, onSaved, onDeleted, onWorldReload, onResidentsChange, addToast }) {
	const [value, setValue] = useState(regionId === null ? NEW_REGION : null);
	const [environment, setEnvironment] = useState(null);
	const [isSaving, setIsSaving] = useState(false);
	const [confirmingDelete, setConfirmingDelete] = useState(false);

	useEffect(() => {
		if (regionId === null) return undefined;
		let active = true;
		const load = async () => {
			const response = await api.get(route('worlds.regions.show', { world: world.id, region: regionId }));
			if (!response.ok) return addToast('Failed to load region', 'error');
			const region = await response.json();
			if (active) setValue({ ...region, settings: region.settings ?? { theme: 'default' } });
		};
		void load();
		return () => { active = false; };
	}, [addToast, regionId, world.id]);

	const reloadTerms = async () => {
		const response = await api.get(route('worlds.regions.show', { world: world.id, region: regionId }));
		if (!response.ok) return addToast('Failed to reload region', 'error');
		const region = await response.json();
		setValue((current) => ({ ...current, activityTerms: region.activityTerms }));
	};

	const save = async () => {
		setIsSaving(true);
		try {
			const form = new FormData();
			form.append('name', value.name); form.append('slug', value.slug); form.append('description', value.description);
			form.append('assistantContextPrompt', value.assistantContextPrompt); form.append('npcContextPrompt', value.npcContextPrompt); form.append('settings', JSON.stringify(value.settings));
			if (environment) form.append('environment', environment);
			const response = regionId === null
				? await api.postForm(route('worlds.regions.store', { world: world.id }), form)
				: await api.patchForm(route('worlds.regions.update', { world: world.id, region: regionId }), form);
			const saved = await response.json();
			if (!response.ok) throw new Error(saved.message);
			addToast('Region saved', 'success');
			reportLayoutWarnings(saved.layoutWarnings, addToast);
			if (saved.removedLinks?.length) addToast(`Links removed for passages no longer in the environment: ${saved.removedLinks.join(', ')}`, 'error');
			setEnvironment(null);
			setValue({ ...saved, settings: saved.settings ?? { theme: 'default' } });
			await onSaved(saved.id);
		} catch (error) { addToast(error.message || 'Failed to save region', 'error'); } finally { setIsSaving(false); }
	};

	const remove = async () => {
		setConfirmingDelete(false);
		try {
			const response = await api.delete(route('worlds.regions.destroy', { world: world.id, region: regionId }));
			if (!response.ok) throw new Error();
			addToast('Region deleted', 'success');
			await onDeleted();
		} catch { addToast('Failed to delete region', 'error'); }
	};

	if (!value) return <p className="text-fg-3 text-xs">Loading region...</p>;

	const regionNames = new Map(world.regions.map((region) => [region.id, region.name]));
	const spawn = world.spawnRegionId ? { regionId: world.spawnRegionId, passageId: world.spawnPassageId } : null;

	return (
		<div className="space-y-5">
			{regionId !== null && (
				<div className="flex justify-end">
					<button type="button" onClick={() => setConfirmingDelete(true)} className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger transition-colors">DELETE REGION</button>
				</div>
			)}
			<RegionForm
				value={value}
				onChange={setValue}
				environmentFile={environment}
				onEnvironmentChange={setEnvironment}
				isSaving={isSaving}
				submitLabel={save}
				imagesEditor={regionId !== null && <WorldImagesEditor routePrefix="worlds.regions.image" routeParams={{ world: world.id, region: regionId }} cardImageUrl={value.cardImageUrl} portraitImageUrl={value.portraitImageUrl} addToast={addToast} />}
				trackEditor={regionId !== null && <WorldTrackEditor worldId={world.id} regionId={regionId} trackOriginalName={value.trackOriginalName} addToast={addToast} />}
			>
				{regionId !== null && (
					<>
						<RegionPassagesEditor worldId={world.id} region={value} regions={world.regions} spawn={spawn} onLinksChange={onWorldReload} addToast={addToast} />
						{inventoryConfig && <RegionObjectsEditor worldId={world.id} region={value} residents={world.residents.filter((resident) => resident.regionId === value.id)} inventoryConfig={inventoryConfig} onTermsChange={reloadTerms} addToast={addToast} />}
						<WorldResidentsEditor worldId={world.id} region={value} residents={world.residents} regionNames={regionNames} inventoryConfig={inventoryConfig} onResidentsChange={onResidentsChange} addToast={addToast} />
					</>
				)}
			</RegionForm>
			{confirmingDelete && (
				<ConfirmationModal
					title="Delete region"
					message={`Delete "${value.name}"? Its environment, its residents and the links to its passages will be removed. Assistants and NPCs will not be affected.`}
					options={[{ label: 'DELETE', value: 'confirm', destructive: true }, { label: 'CANCEL', value: 'cancel', cancel: true }]}
					onSelect={(selected) => { if (selected === 'confirm') void remove(); else setConfirmingDelete(false); }}
				/>
			)}
		</div>
	);
}

export default function RegionsTab({ world, inventoryConfig, onWorldReload, onResidentsChange, addToast }) {
	const [selectedId, setSelectedId] = useState(world.regions[0]?.id ?? null);

	const selectSaved = async (regionId) => {
		await onWorldReload();
		setSelectedId(regionId);
	};

	const afterDelete = async () => {
		const remaining = world.regions.filter((region) => region.id !== selectedId);
		setSelectedId(remaining[0]?.id ?? null);
		await onWorldReload();
	};

	return (
		<div className="grid grid-cols-1 md:grid-cols-[14rem_1fr] gap-5">
			<div className="space-y-1">
				<p className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase mb-2">Regions</p>
				{world.regions.map((region) => (
					<button
						key={region.id}
						type="button"
						onClick={() => setSelectedId(region.id)}
						className={`w-full text-left border px-3 py-2 text-sm transition-colors cursor-pointer ${selectedId === region.id ? 'border-accent text-accent bg-bg-1' : 'border-line-1 text-fg-1 hover:border-accent/50'}`}
					>
						{world.spawnRegionId === region.id && <span className="text-accent" title="Spawn point">★ </span>}
						{region.name}
						{hasWarning(region) && <span className="text-warning" title="Unlinked passages or no passages"> ⚠</span>}
					</button>
				))}
				<button type="button" onClick={() => setSelectedId(null)} className={`w-full text-left border border-dashed px-3 py-2 text-[0.7rem] tracking-[0.1em] transition-colors cursor-pointer ${selectedId === null ? 'border-accent text-accent' : 'border-line-1 text-fg-3 hover:text-fg-1'}`}>
					+ ADD REGION
				</button>
			</div>
			<RegionEditor key={selectedId ?? 'new'} world={world} inventoryConfig={inventoryConfig} regionId={selectedId} onSaved={selectSaved} onDeleted={afterDelete} onWorldReload={onWorldReload} onResidentsChange={onResidentsChange} addToast={addToast} />
		</div>
	);
}
