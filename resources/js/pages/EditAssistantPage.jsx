import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import PromptEditor from '../components/PromptEditor.jsx';
import EmotionGrid from '../components/EmotionGrid.jsx';
import VrmEmotionEditor from '../components/VrmEmotionEditor.jsx';
import PoseEditor from '../components/PoseEditor.jsx';
import ConfirmationModal from '../components/common/ConfirmationModal.jsx';
import usePrompt from '../hooks/usePrompt.js';

export default function EditAssistantPage() {
	const { assistantId: id } = useParams();
	const navigate = useNavigate();
	const { addToast } = useOutletContext();

	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);
	const [name, setName] = useState('');
	const [slug, setSlug] = useState('');
	const [description, setDescription] = useState('');
	const [openingMessage, setOpeningMessage] = useState('');
	const [emotions, setEmotions] = useState([]);
	const [restrictedEmotions, setRestrictedEmotions] = useState([]);
	const [poses, setPoses] = useState([]);
	const [defaultPreview, setDefaultPreview] = useState(null);
	const defaultImageRef = useRef(null);
	const [archives, setArchives] = useState([]);
	const [selectedArchiveId, setSelectedArchiveId] = useState('');
	const [mode, setMode] = useState('assistant');
	const [portraitType, setPortraitType] = useState('image');
	const [vrmFilename, setVrmFilename] = useState(null);
	const [isUploadingVrm, setIsUploadingVrm] = useState(false);
	const [isDeletingVrm, setIsDeletingVrm] = useState(false);
	const [confirmingVrmDelete, setConfirmingVrmDelete] = useState(false);
	const vrmInputRef = useRef(null);
	const [cardImagePreview, setCardImagePreview] = useState(null);
	const [isUploadingCardImage, setIsUploadingCardImage] = useState(false);
	const cardImageRef = useRef(null);
	const [promptMode, setPromptMode] = useState('manual');
	const [promptJson, setPromptJson] = useState('');
	const [promptJsonError, setPromptJsonError] = useState(null);

	const prompt = usePrompt(Number(id), addToast);

	useEffect(() => {
		api.get(route('archives.index'))
			.then((res) => res.json())
			.then(setArchives)
			.catch(() => {});
	}, []);

	// Load assistant data
	useEffect(() => {
		const load = async () => {
			try {
				const res = await api.get(route('assistants.show', { id }));
				if (!res.ok) {
					navigate('/assistants', { replace: true });
					return;
				}
				const data = await res.json();
				setName(data.name);
				setSlug(data.slug);
				setDescription(data.description || '');
				setOpeningMessage(data.opening_message || '');
				setSelectedArchiveId(data.archive_id ? String(data.archive_id) : '');
				setMode(data.mode || 'assistant');
				setPortraitType(data.portrait_type || 'image');
				setVrmFilename(data.vrm_url ? (data.vrm_original_name || 'avatar.vrm') : null);
				setCardImagePreview(data.image_url || null);
				const loadedEmotions = data.emotions || [];
				setEmotions(loadedEmotions);
				setRestrictedEmotions(data.restricted_emotions || []);
				setPoses(data.poses || []);
				const defaultEmo = loadedEmotions.find((e) => e.name === 'default');
				if (defaultEmo?.image_url) setDefaultPreview(defaultEmo.image_url);
			} catch {
				addToast('Failed to load assistant', 'error');
				navigate('/assistants', { replace: true });
			} finally {
				setIsLoading(false);
			}
		};
		void load();
	}, [id]);

	const handleSave = async () => {
		if (!name.trim() || !slug.trim()) {
			addToast('Name and slug are required', 'error');
			return;
		}

		setIsSaving(true);
		try {
			const res = await api.patch(route('assistants.update', { id }), {
				name: name.trim(),
				slug: slug.trim(),
				description: description.trim() || null,
				opening_message: openingMessage.trim() || null,
				archive_id: selectedArchiveId ? Number(selectedArchiveId) : null,
				mode,
				portrait_type: portraitType,
			});

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			addToast('Assistant saved', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to save assistant', 'error');
		} finally {
			setIsSaving(false);
		}
	};

	const handleVrmUpload = async (e) => {
		const file = e.target.files?.[0];
		if (!file) return;
		setIsUploadingVrm(true);
		try {
			const formData = new FormData();
			formData.append('vrm', file);
			const res = await api.postForm(route('assistants.vrm.store', { id }), formData);
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Upload failed');
			}
			setVrmFilename(file.name);
			addToast('VRM file uploaded', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to upload VRM file', 'error');
		} finally {
			setIsUploadingVrm(false);
			if (vrmInputRef.current) vrmInputRef.current.value = '';
		}
	};

	const handleVrmDelete = async () => {
		setConfirmingVrmDelete(false);
		setIsDeletingVrm(true);
		try {
			const res = await api.delete(route('assistants.vrm.destroy', { id }));
			if (!res.ok) throw new Error('Delete failed');
			setVrmFilename(null);
			addToast('VRM file deleted', 'success');
		} catch {
			addToast('Failed to delete VRM file', 'error');
		} finally {
			setIsDeletingVrm(false);
		}
	};

	const handleCardImageUpload = async (e) => {
		const file = e.target.files?.[0];
		if (!file) return;
		setIsUploadingCardImage(true);
		try {
			const formData = new FormData();
			formData.append('image', file);
			const res = await api.postForm(route('assistants.image.store', { id }), formData);
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Upload failed');
			}
			setCardImagePreview(URL.createObjectURL(file));
			addToast('Card image updated', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to upload card image', 'error');
		} finally {
			setIsUploadingCardImage(false);
			if (cardImageRef.current) cardImageRef.current.value = '';
		}
	};

	/* ── VRM emotion→blendshape handlers ── */

	const handleAddVrmEmotion = async (name, blendshapes, restricted) => {
		try {
			const res = await api.post(route('assistants.emotions.store', { assistant: id }), {
				name,
				vrm_blendshapes: blendshapes,
				restricted,
			});
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Failed to add emotion');
			}
			const data = await res.json();
			if (restricted) {
				setRestrictedEmotions((prev) => [...prev, data]);
			} else {
				setEmotions((prev) => [...prev, data]);
			}
			addToast('Emotion added', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to add emotion', 'error');
		}
	};

	const handleUpdateVrmBlendshapes = async (emotion, blendshapes) => {
		try {
			const res = await api.post(route('assistants.emotions.update', { assistant: id, emotion: emotion.id }), {
				vrm_blendshapes: blendshapes,
			});
			if (!res.ok) throw new Error('Update failed');
			const data = await res.json();
			setEmotions((prev) => prev.map((e) => (e.id === emotion.id ? data : e)));
			setRestrictedEmotions((prev) => prev.map((e) => (e.id === emotion.id ? data : e)));
			addToast('Expression mapping saved', 'success');
		} catch {
			addToast('Failed to save expression mapping', 'error');
		}
	};

	/* ── Emotion handlers (individual API calls) ── */

	const handleReplaceDefaultImage = async (e) => {
		const file = e.target.files?.[0];
		if (!file) return;

		const defaultEmotion = emotions.find((em) => em.name === 'default');
		if (!defaultEmotion) return;

		await handleReplaceImage(defaultEmotion, file);
		setDefaultPreview(URL.createObjectURL(file));
	};

	const handleAddEmotion = async (emotionName, file) => {
		const formData = new FormData();
		formData.append('name', emotionName);
		formData.append('image', file);

		try {
			const res = await api.postForm(
				route('assistants.emotions.store', { assistant: id }),
				formData
			);
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Failed to add emotion');
			}
			const data = await res.json();
			setEmotions((prev) => [...prev, data]);
			addToast('Emotion added', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to add emotion', 'error');
		}
	};

	const handleAddRestrictedEmotion = async (emotionName, file) => {
		const formData = new FormData();
		formData.append('name', emotionName);
		formData.append('image', file);
		formData.append('restricted', '1');

		try {
			const res = await api.postForm(
				route('assistants.emotions.store', { assistant: id }),
				formData
			);
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Failed to add emotion');
			}
			const data = await res.json();
			setRestrictedEmotions((prev) => [...prev, data]);
			addToast('Restricted emotion added', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to add emotion', 'error');
		}
	};

	const handleDeleteEmotion = async (emotion) => {
		try {
			const res = await api.delete(
				route('assistants.emotions.destroy', { assistant: id, emotion: emotion.id })
			);
			if (!res.ok) throw new Error('Delete failed');
			setEmotions((prev) => prev.filter((e) => e.id !== emotion.id));
			setRestrictedEmotions((prev) => prev.filter((e) => e.id !== emotion.id));
			addToast('Emotion deleted', 'success');
		} catch {
			addToast('Failed to delete emotion', 'error');
		}
	};

	const handleReplaceImage = async (emotion, file) => {
		const formData = new FormData();
		formData.append('image', file);

		try {
			const res = await api.postForm(
				route('assistants.emotions.update', { assistant: id, emotion: emotion.id }),
				formData
			);
			if (!res.ok) throw new Error('Update failed');
			const data = await res.json();
			setEmotions((prev) => prev.map((e) => (e.id === emotion.id ? data : e)));
			setRestrictedEmotions((prev) => prev.map((e) => (e.id === emotion.id ? data : e)));
			addToast('Image updated', 'success');
		} catch {
			addToast('Failed to update emotion', 'error');
		}
	};

	/* ── Pose handlers ── */

	const handleAddPose = async (poseName, blendshapes) => {
		try {
			const res = await api.post(route('assistants.poses.store', { assistant: id }), {
				name: poseName,
				vrm_blendshapes: blendshapes,
			});
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Failed to add pose');
			}
			const data = await res.json();
			setPoses((prev) => [...prev, data]);
			addToast('Pose added', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to add pose', 'error');
		}
	};

	const handleUpdatePoseBlendshapes = async (pose, blendshapes) => {
		try {
			const res = await api.post(route('assistants.poses.update', { assistant: id, pose: pose.id }), {
				vrm_blendshapes: blendshapes,
			});
			if (!res.ok) throw new Error('Update failed');
			const data = await res.json();
			setPoses((prev) => prev.map((p) => (p.id === pose.id ? data : p)));
			addToast('Pose expression saved', 'success');
		} catch {
			addToast('Failed to save pose expression', 'error');
		}
	};

	const handleDeletePose = async (pose) => {
		try {
			const res = await api.delete(route('assistants.poses.destroy', { assistant: id, pose: pose.id }));
			if (!res.ok) throw new Error('Delete failed');
			setPoses((prev) => prev.filter((p) => p.id !== pose.id));
			addToast('Pose deleted', 'success');
		} catch {
			addToast('Failed to delete pose', 'error');
		}
	};

	const handleUploadPoseAnimation = async (pose, file) => {
		const formData = new FormData();
		formData.append('animation', file);

		try {
			const res = await api.postForm(route('assistants.poses.animation.store', { assistant: id, pose: pose.id }), formData);
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Upload failed');
			}
			const data = await res.json();
			setPoses((prev) => prev.map((p) => (p.id === pose.id ? { ...p, animation_url: data.animation_url } : p)));
			addToast('Pose animation uploaded', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to upload pose animation', 'error');
		}
	};

	const handleDeletePoseAnimation = async (pose) => {
		try {
			const res = await api.delete(route('assistants.poses.animation.destroy', { assistant: id, pose: pose.id }));
			if (!res.ok) throw new Error('Delete failed');
			setPoses((prev) => prev.map((p) => (p.id === pose.id ? { ...p, animation_url: null } : p)));
			addToast('Pose animation deleted', 'success');
		} catch {
			addToast('Failed to delete pose animation', 'error');
		}
	};

	if (isLoading || prompt.isLoading) {
		return (
			<>
				<Header
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					onBack={() => navigate('/assistants')}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Edit Assistant</span>
				</Header>
				<div className="flex-1 p-5">
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				</div>
			</>
		);
	}

	return (
		<>
			<Header
				status={{
					label: isSaving ? 'SAVING' : 'WAITING',
					color: isSaving ? 'text-warning' : 'text-info',
					dot: '●',
					blink: isSaving,
				}}
				onBack={() => navigate('/assistants')}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">
					Edit — {name}
				</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-6">
				{/* Basic fields */}
				<div className="space-y-4">
					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Name
						</label>
						<input
							type="text"
							value={name}
							onChange={(e) => setName(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
						/>
					</div>

					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Slug
						</label>
						<input
							type="text"
							value={slug}
							onChange={(e) => setSlug(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
						/>
					</div>

					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Description
						</label>
						<textarea
							value={description}
							onChange={(e) => setDescription(e.target.value)}
							rows={3}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
						/>
					</div>

					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Opening Message
						</label>
						<textarea
							value={openingMessage}
							onChange={(e) => setOpeningMessage(e.target.value)}
							rows={3}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
						/>
					</div>

					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Archive
						</label>
						<select
							value={selectedArchiveId}
							onChange={(e) => setSelectedArchiveId(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
						>
							<option value="">— None —</option>
							{archives.map((a) => (
								<option key={a.id} value={a.id}>{a.name}</option>
							))}
						</select>
					</div>

					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Mode
						</label>
						<select
							value={mode}
							onChange={(e) => setMode(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
						>
							<option value="assistant">Assistant</option>
							<option value="agent">Agent</option>
						</select>
					</div>

					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Portrait Type
						</label>
						<select
							value={portraitType}
							onChange={(e) => setPortraitType(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
						>
							<option value="image">Image</option>
							<option value="avatar3d">3D Avatar</option>
						</select>
					</div>

					{portraitType === 'avatar3d' && (
						<>
							<div className="space-y-2">
								<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
									VRM File
								</label>
								{vrmFilename ? (
									<div className="flex items-center gap-2">
										<span className="text-accent text-sm truncate flex-1">{vrmFilename}</span>
										<button
											onClick={() => setConfirmingVrmDelete(true)}
											disabled={isDeletingVrm}
											className="text-[0.65rem] tracking-[0.1em] px-3 py-1 border border-danger text-danger hover:bg-danger/10 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-default"
										>
											{isDeletingVrm ? 'DELETING...' : 'DELETE'}
										</button>
									</div>
								) : (
									<button
										onClick={() => vrmInputRef.current?.click()}
										disabled={isUploadingVrm}
										className="text-[0.65rem] tracking-[0.1em] px-3 py-1 border border-line-1 text-fg-3 hover:border-fg-3 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-default"
									>
										{isUploadingVrm ? 'UPLOADING...' : 'UPLOAD .VRM'}
									</button>
								)}
								<input
									ref={vrmInputRef}
									type="file"
									accept=".vrm"
									onChange={handleVrmUpload}
									className="hidden"
								/>
							</div>

							<div className="space-y-2">
								<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
									Card Image <span className="text-fg-3 normal-case">(shown in the assistants menu)</span>
								</label>
								<div
									onClick={() => cardImageRef.current?.click()}
									className="w-32 h-32 border border-dashed border-line-1 flex items-center justify-center cursor-pointer hover:border-accent/50 transition-colors overflow-hidden"
								>
									{cardImagePreview ? (
										<img src={cardImagePreview} alt="Card" className="w-full h-full object-cover object-top" />
									) : (
										<span className="text-fg-3 text-[0.65rem] tracking-[0.1em] text-center px-2">
											{isUploadingCardImage ? 'UPLOADING...' : 'CLICK TO SELECT'}
										</span>
									)}
								</div>
								<input
									ref={cardImageRef}
									type="file"
									accept="image/*"
									onChange={handleCardImageUpload}
									className="hidden"
								/>
							</div>
						</>
					)}

					{/* Save basic fields */}
					<div className="flex justify-end">
						<button
							onClick={handleSave}
							disabled={isSaving}
							className={`text-[0.75rem] tracking-[0.1em] px-6 py-2 transition-colors ${
								isSaving
									? 'bg-bg-3 text-fg-3 cursor-default'
									: 'button-success cursor-pointer'
							}`}
						>
							{isSaving ? 'SAVING...' : 'SAVE'}
						</button>
					</div>
				</div>

				{portraitType === 'image' && (
					<>
						<div className="border-t border-line-1" />

						{/* Default image */}
						<div className="space-y-2">
							<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
								Default Image
							</label>
							<div
								onClick={() => defaultImageRef.current?.click()}
								className="w-32 h-32 border border-dashed border-line-1 flex items-center justify-center cursor-pointer hover:border-accent/50 transition-colors overflow-hidden"
							>
								{defaultPreview ? (
									<img src={defaultPreview} alt="Default" className="w-full h-full object-cover object-top" />
								) : (
									<span className="text-fg-3 text-[0.65rem] tracking-[0.1em] text-center px-2">
										CLICK TO REPLACE
									</span>
								)}
							</div>
							<input
								ref={defaultImageRef}
								type="file"
								accept="image/*"
								onChange={handleReplaceDefaultImage}
								className="hidden"
							/>
						</div>

						{/* Emotions */}
						<EmotionGrid
							emotions={emotions.filter((e) => e.name !== 'default')}
							onAdd={handleAddEmotion}
							onDelete={handleDeleteEmotion}
							onUpdateImage={handleReplaceImage}
						/>

						{/* Restricted Emotions */}
						<EmotionGrid
							label="Restricted Emotions"
							emotions={restrictedEmotions}
							onAdd={handleAddRestrictedEmotion}
							onDelete={handleDeleteEmotion}
							onUpdateImage={handleReplaceImage}
						/>
					</>
				)}

				{portraitType === 'avatar3d' && (
					<>
						<div className="border-t border-line-1" />

						<VrmEmotionEditor
							emotions={emotions}
							onAdd={(name, blendshapes) => handleAddVrmEmotion(name, blendshapes, false)}
							onDelete={handleDeleteEmotion}
							onUpdateBlendshapes={handleUpdateVrmBlendshapes}
						/>

						<VrmEmotionEditor
							label="Restricted Emotions"
							emotions={restrictedEmotions}
							onAdd={(name, blendshapes) => handleAddVrmEmotion(name, blendshapes, true)}
							onDelete={handleDeleteEmotion}
							onUpdateBlendshapes={handleUpdateVrmBlendshapes}
						/>

						<div className="border-t border-line-1" />

						<PoseEditor
							poses={poses}
							onAdd={handleAddPose}
							onDelete={handleDeletePose}
							onUpdateBlendshapes={handleUpdatePoseBlendshapes}
							onUploadAnimation={handleUploadPoseAnimation}
							onDeleteAnimation={handleDeletePoseAnimation}
						/>
					</>
				)}

				<div className="border-t border-line-1" />

				{/* Prompt */}
				<div>
					<div className="flex items-center justify-between mb-3">
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase">
							Prompt
						</label>
						<div className="flex gap-1">
							{['manual', 'json'].map((mode) => (
								<button
									key={mode}
									onClick={() => {
										if (mode === 'json' && promptMode !== 'json') {
											setPromptJson(prompt.sections ? JSON.stringify(prompt.sections, null, 2) : '');
											setPromptJsonError(null);
										}
										setPromptMode(mode);
									}}
									className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border transition-colors cursor-pointer ${
										promptMode === mode
											? 'border-accent text-accent bg-accent/10'
											: 'border-line-1 text-fg-3 hover:border-fg-3'
									}`}
								>
									{mode === 'manual' ? 'Manual' : 'Edit JSON'}
								</button>
							))}
						</div>
					</div>

					{promptMode === 'manual' ? (
						<PromptEditor {...prompt} />
					) : (
						<div>
							<textarea
								value={promptJson}
								onChange={(e) => {
									setPromptJson(e.target.value);
									try {
										JSON.parse(e.target.value);
										setPromptJsonError(null);
									} catch {
										setPromptJsonError('Invalid JSON');
									}
								}}
								rows={20}
								className={`w-full bg-bg-1 border text-accent text-sm px-3 py-2 outline-none transition-colors resize-y font-mono ${
									promptJsonError ? 'border-danger' : 'border-line-1 focus:border-accent/50'
								}`}
								placeholder="{}"
							/>
							{promptJsonError && (
								<p className="text-danger text-[0.65rem] mt-1">{promptJsonError}</p>
							)}
							<div className="flex justify-end pt-3">
								<button
									onClick={() => prompt.saveFromJson(promptJson)}
									disabled={!!promptJsonError || prompt.isSaving}
									className={`text-[0.75rem] tracking-[0.1em] px-6 py-2 transition-colors ${
										promptJsonError || prompt.isSaving
											? 'bg-bg-3 text-fg-3 cursor-default'
											: 'button-success cursor-pointer'
									}`}
								>
									{prompt.isSaving ? 'SAVING...' : 'SAVE PROMPT'}
								</button>
							</div>
						</div>
					)}
				</div>
			</div>

			{confirmingVrmDelete && (
				<ConfirmationModal
					message="Delete the VRM avatar file?"
					onConfirm={handleVrmDelete}
					onCancel={() => setConfirmingVrmDelete(false)}
				/>
			)}
		</>
	);
}