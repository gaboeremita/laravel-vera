import { useRef, useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { FIELD_LABEL, FIELD_INPUT } from '../utils/formFieldStyles.js';
import Header from '../components/Header.jsx';
import WorldForm from '../components/WorldForm.jsx';
import WorldResidentsEditor from '../components/WorldResidentsEditor.jsx';
import { ImageUploadField } from '../components/WorldImagesEditor.jsx';

export default function CreateWorldPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const [value, setValue] = useState({ name: '', slug: '', description: '', assistantContextPrompt: '', npcContextPrompt: '', settings: { theme: 'default' }, residents: [] });
	const [environment, setEnvironment] = useState(null);
	const [cardImageFile, setCardImageFile] = useState(null);
	const [cardImagePreview, setCardImagePreview] = useState(null);
	const [portraitImageFile, setPortraitImageFile] = useState(null);
	const [portraitImagePreview, setPortraitImagePreview] = useState(null);
	const [trackFile, setTrackFile] = useState(null);
	const [isSaving, setIsSaving] = useState(false);
	const trackInputRef = useRef(null);

	const selectCardImage = (file) => { setCardImageFile(file); setCardImagePreview(URL.createObjectURL(file)); };
	const selectPortraitImage = (file) => { setPortraitImageFile(file); setPortraitImagePreview(URL.createObjectURL(file)); };

	const uploadStagedFile = async (routeName, worldId, fieldName, file) => {
		const form = new FormData();
		form.append(fieldName, file);
		const response = await api.postForm(route(routeName, { world: worldId }), form);
		return response.ok;
	};

	const save = async () => {
		if (!environment) return addToast('A GLB environment is required', 'error');
		setIsSaving(true);
		try {
			const form = new FormData();
			form.append('name', value.name); form.append('slug', value.slug); form.append('description', value.description);
			form.append('assistantContextPrompt', value.assistantContextPrompt); form.append('npcContextPrompt', value.npcContextPrompt); form.append('settings', JSON.stringify(value.settings)); form.append('environment', environment);
			const response = await api.postForm(route('worlds.store'), form);
			if (!response.ok) throw new Error((await response.json()).message);
			const world = await response.json();

			const failures = [];
			for (const resident of value.residents) {
				const residentResponse = await api.put(route('worlds.residents.upsert', { world: world.id, assistant: resident.assistant.id }), { position: resident.position, behavior: resident.behavior, openingMessage: resident.openingMessage, customPrompt: resident.customPrompt });
				if (!residentResponse.ok) failures.push(resident.assistant.name);
			}
			if (cardImageFile && !(await uploadStagedFile('worlds.image.card.store', world.id, 'image', cardImageFile))) failures.push('card image');
			if (portraitImageFile && !(await uploadStagedFile('worlds.image.portrait.store', world.id, 'image', portraitImageFile))) failures.push('portrait image');
			if (trackFile && !(await uploadStagedFile('worlds.track.store', world.id, 'track', trackFile))) failures.push('background music');
			if (failures.length > 0) addToast(`World saved, but ${failures.join(', ')} failed to attach — add them on the edit screen`, 'error');

			navigate(`/worlds/${world.id}/edit`);
		} catch (error) { addToast(error.message || 'Failed to create world', 'error'); } finally { setIsSaving(false); }
	};

	const imagesEditor = (
		<div>
			<label className={FIELD_LABEL}>Images</label>
			<div className="flex flex-wrap gap-6 pl-4 border-l border-line-1">
				<ImageUploadField label="Card Image" hint="shown in the worlds menu" previewUrl={cardImagePreview} onUpload={selectCardImage} />
				<ImageUploadField label="Portrait Image" hint="shown while browsing this world's sessions" previewUrl={portraitImagePreview} onUpload={selectPortraitImage} />
			</div>
		</div>
	);

	const trackEditor = (
		<div>
			<label className={FIELD_LABEL}>Background Music (.mp3, .wav)</label>
			<div onClick={() => trackInputRef.current?.click()} className={`${FIELD_INPUT} cursor-pointer flex items-center justify-between gap-3`}>
				<span className="truncate">{trackFile?.name ?? 'No file chosen'}</span>
				{trackFile && (
					<button type="button" onClick={(event) => { event.stopPropagation(); setTrackFile(null); }} className="text-danger text-[0.65rem] tracking-[0.1em] shrink-0 cursor-pointer hover:text-danger transition-colors">
						REMOVE
					</button>
				)}
			</div>
			<input
				ref={trackInputRef}
				type="file"
				accept="audio/mpeg,audio/wav,.mp3,.wav"
				onChange={(event) => {
					const file = event.target.files?.[0];
					if (file) setTrackFile(file);
					event.target.value = '';
				}}
				className="hidden"
			/>
		</div>
	);

	return <><Header hideSettings onBack={() => navigate('/worlds')}><span className="text-fg-2 text-lg tracking-[0.05em]">Create World</span></Header><WorldForm value={value} onChange={setValue} environmentFile={environment} onEnvironmentChange={setEnvironment} isSaving={isSaving} submitLabel={save} imagesEditor={imagesEditor} trackEditor={trackEditor}><WorldResidentsEditor world={value} onWorldChange={setValue} addToast={addToast} /></WorldForm></>;
}
