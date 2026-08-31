import { useState, useRef, useEffect } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import PromptEditor from '../components/PromptEditor.jsx';
import EmotionGrid from '../components/EmotionGrid.jsx';
import PoseEditor from '../components/PoseEditor.jsx';
import DefaultPoseEditor from '../components/DefaultPoseEditor.jsx';
import useLocalPrompt from '../hooks/useLocalPrompt.js';

export default function CreateAssistantPage({ kind = 'assistant' }) {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const isNpc = kind === 'world_npc';
	const collectionPath = isNpc ? '/npcs' : '/assistants';

	const [name, setName] = useState('');
	const [slug, setSlug] = useState('');
	const [description, setDescription] = useState('');
	const [openingMessage, setOpeningMessage] = useState('');
	const [isSaving, setIsSaving] = useState(false);

	// Default image — dedicated upload
	const [defaultImage, setDefaultImage] = useState(null);
	const [defaultPreview, setDefaultPreview] = useState(null);
	const defaultImageRef = useRef(null);

	// Staged emotions — local File objects, not yet uploaded
	const [stagedEmotions, setStagedEmotions] = useState([]);
	const [stagedRestrictedEmotions, setStagedRestrictedEmotions] = useState([]);

	// Staged poses (avatar3d mode) — blendshapes and/or a pending animation file, not yet uploaded
	const [stagedPoses, setStagedPoses] = useState([]);
	const stagedPoseFilesRef = useRef({});

	// Archive
	const [archives, setArchives] = useState([]);
	const [selectedArchiveId, setSelectedArchiveId] = useState('');

	// Agent mode
	const [assistantMode, setAssistantMode] = useState('assistant');

	// Portrait type
	const [portraitType, setPortraitType] = useState(isNpc ? 'avatar3d' : 'image');
	const [pendingVrmFile, setPendingVrmFile] = useState(null);

	useEffect(() => {
		api.get(route('archives.index'))
			.then((res) => res.json())
			.then(setArchives)
			.catch(() => {});
	}, []);

	// Prompt input mode
	const [promptMode, setPromptMode] = useState('manual');
	const [promptJson, setPromptJson] = useState('');
	const [promptJsonError, setPromptJsonError] = useState(null);

	// Prompt tree — local state, no API
	const prompt = useLocalPrompt();

	const handleDefaultImage = (e) => {
		const file = e.target.files?.[0];
		if (!file) return;
		if (defaultPreview) URL.revokeObjectURL(defaultPreview);
		setDefaultImage(file);
		setDefaultPreview(URL.createObjectURL(file));
	};

	const handleAddEmotion = (emotionName, file) => {
		const preview = URL.createObjectURL(file);
		setStagedEmotions((prev) => [...prev, { name: emotionName, file, preview }]);
	};

	const handleDeleteEmotion = (emotion) => {
		setStagedEmotions((prev) => prev.filter((e) => e !== emotion));
		if (emotion.preview) URL.revokeObjectURL(emotion.preview);
	};

	const handleReplaceImage = (emotion, file) => {
		const preview = URL.createObjectURL(file);
		setStagedEmotions((prev) =>
			prev.map((e) => {
				if (e !== emotion) return e;
				if (e.preview) URL.revokeObjectURL(e.preview);
				return { ...e, file, preview };
			})
		);
	};

	const handleAddRestrictedEmotion = (emotionName, file) => {
		const preview = URL.createObjectURL(file);
		setStagedRestrictedEmotions((prev) => [...prev, { name: emotionName, file, preview }]);
	};

	const handleDeleteRestrictedEmotion = (emotion) => {
		setStagedRestrictedEmotions((prev) => prev.filter((e) => e !== emotion));
		if (emotion.preview) URL.revokeObjectURL(emotion.preview);
	};

	const handleReplaceRestrictedImage = (emotion, file) => {
		const preview = URL.createObjectURL(file);
		setStagedRestrictedEmotions((prev) =>
			prev.map((e) => {
				if (e !== emotion) return e;
				if (e.preview) URL.revokeObjectURL(e.preview);
				return { ...e, file, preview };
			})
		);
	};

	const handleAddPose = (poseName, blendshapes, file) => {
		const localId = crypto.randomUUID();
		if (file) {
			stagedPoseFilesRef.current[localId] = file;
		}
		setStagedPoses((prev) => [
			...prev,
			{
				id: localId,
				name: poseName,
				vrm_blendshapes: blendshapes,
				animation_url: file ? URL.createObjectURL(file) : null,
				animation_original_name: file ? file.name : null,
			},
		]);
	};

	const handleDeletePose = (pose) => {
		setStagedPoses((prev) => prev.filter((p) => p.id !== pose.id));
		if (pose.animation_url) URL.revokeObjectURL(pose.animation_url);
		delete stagedPoseFilesRef.current[pose.id];
	};

	const handleUpdatePoseBlendshapes = (pose, name, blendshapes) => {
		setStagedPoses((prev) => prev.map((p) => (p.id === pose.id ? { ...p, name, vrm_blendshapes: blendshapes } : p)));
	};

	const handleUploadPoseAnimation = (pose, file) => {
		stagedPoseFilesRef.current[pose.id] = file;
		const preview = URL.createObjectURL(file);
		setStagedPoses((prev) => prev.map((p) => (p.id === pose.id ? { ...p, animation_url: preview, animation_original_name: file.name } : p)));
	};

	const handleDeletePoseAnimation = (pose) => {
		delete stagedPoseFilesRef.current[pose.id];
		if (pose.animation_url) URL.revokeObjectURL(pose.animation_url);
		setStagedPoses((prev) => prev.map((p) => (p.id === pose.id ? { ...p, animation_url: null } : p)));
	};

	/* ── Default pose (staged like any other pose, but name-locked and never shown in the general list) ── */

	const handleUpdateDefaultPoseBlendshapes = (blendshapes) => {
		setStagedPoses((prev) => {
			if (prev.some((p) => p.name === 'default')) {
				return prev.map((p) => (p.name === 'default' ? { ...p, vrm_blendshapes: blendshapes } : p));
			}
			return [...prev, { id: crypto.randomUUID(), name: 'default', vrm_blendshapes: blendshapes, animation_url: null, animation_original_name: null }];
		});
	};

	const handleUploadDefaultPoseAnimation = (file) => {
		const preview = URL.createObjectURL(file);
		setStagedPoses((prev) => {
			const existing = prev.find((p) => p.name === 'default');
			const localId = existing?.id ?? crypto.randomUUID();
			stagedPoseFilesRef.current[localId] = file;
			if (existing) {
				return prev.map((p) => (p.name === 'default' ? { ...p, animation_url: preview, animation_original_name: file.name } : p));
			}
			return [...prev, { id: localId, name: 'default', vrm_blendshapes: null, animation_url: preview, animation_original_name: file.name }];
		});
	};

	const handleDeleteDefaultPoseAnimation = () => {
		setStagedPoses((prev) => {
			const existing = prev.find((p) => p.name === 'default');
			if (existing) {
				delete stagedPoseFilesRef.current[existing.id];
				if (existing.animation_url) URL.revokeObjectURL(existing.animation_url);
			}
			return prev.map((p) => (p.name === 'default' ? { ...p, animation_url: null, animation_original_name: null } : p));
		});
	};

	const handleSubmit = async () => {
		if (!name.trim() || (!isNpc && !slug.trim())) {
			addToast(isNpc ? 'Name is required' : 'Name and slug are required', 'error');
			return;
		}

		if (isNpc && !pendingVrmFile) {
			addToast('NPCs require a 3D avatar file', 'error');
			return;
		}

		if (portraitType === 'image' && !defaultImage) {
			addToast('A default image is required', 'error');
			return;
		}

		setIsSaving(true);

		try {
			const formData = new FormData();
			formData.append('name', name.trim());
			if (!isNpc) formData.append('slug', slug.trim());
			formData.append('description', description.trim());
			formData.append('opening_message', openingMessage.trim());
			formData.append('mode', isNpc ? 'assistant' : assistantMode);
			formData.append('portrait_type', portraitType);
			if (isNpc) formData.append('vrm', pendingVrmFile);

			if (promptMode === 'json') {
				try {
					const parsed = JSON.parse(promptJson);
					formData.append('prompt', JSON.stringify(parsed));
				} catch {
					addToast('Prompt JSON is not valid', 'error');
					setIsSaving(false);
					return;
				}
			} else if (prompt.sections) {
				formData.append('prompt', JSON.stringify(prompt.sections));
			}

			if (selectedArchiveId) {
				formData.append('archive_id', selectedArchiveId);
			}

			if (portraitType === 'image') {
				formData.append('emotions[0][name]', 'default');
				formData.append('emotions[0][image]', defaultImage);

				stagedEmotions.forEach((emotion, i) => {
					formData.append(`emotions[${i + 1}][name]`, emotion.name);
					formData.append(`emotions[${i + 1}][image]`, emotion.file);
				});

				stagedRestrictedEmotions.forEach((emotion, i) => {
					formData.append(`restricted_emotions[${i}][name]`, emotion.name);
					formData.append(`restricted_emotions[${i}][image]`, emotion.file);
				});
			} else {
				stagedPoses.forEach((pose, i) => {
					formData.append(`poses[${i}][name]`, pose.name);
					(pose.vrm_blendshapes || []).forEach((b, j) => {
						formData.append(`poses[${i}][vrm_blendshapes][${j}][expression]`, b.expression);
						formData.append(`poses[${i}][vrm_blendshapes][${j}][weight]`, b.weight);
					});
					const pendingFile = stagedPoseFilesRef.current[pose.id];
					if (pendingFile) {
						formData.append(`poses[${i}][animation]`, pendingFile);
					}
				});
			}

			const res = await api.postForm(route(isNpc ? 'npcs.store' : 'assistants.store'), formData);

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Failed to create assistant');
			}

			const created = await res.json();

			if (!isNpc && portraitType === 'avatar3d' && pendingVrmFile && created.id) {
				const vrmForm = new FormData();
				vrmForm.append('vrm', pendingVrmFile);
				const vrmRes = await api.postForm(route('assistants.vrm.store', { id: created.id }), vrmForm);
				if (!vrmRes.ok) {
					const vrmError = await vrmRes.json().catch(() => ({}));
					addToast(vrmError.message || `${isNpc ? 'NPC' : 'Assistant'} created, but the VRM upload failed`, 'error');
					navigate(collectionPath);
					return;
				}
			}

			addToast(`${isNpc ? 'NPC' : 'Assistant'} created`, 'success');
			navigate(collectionPath);
		} catch (e) {
			addToast(e.message || `Failed to create ${isNpc ? 'NPC' : 'assistant'}`, 'error');
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<>
			<Header
				status={{
					label: isSaving ? 'SAVING' : 'WAITING',
					color: isSaving ? 'text-warning' : 'text-info',
					dot: '●',
					blink: isSaving,
				}}
				onBack={() => navigate(collectionPath)}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">New {isNpc ? 'NPC' : 'Assistant'}</span>
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
							placeholder="e.g. VERA"
						/>
					</div>

					{!isNpc && <div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Slug
						</label>
						<input
							type="text"
							value={slug}
							onChange={(e) => setSlug(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
							placeholder="e.g. vera"
						/>
					</div>}

					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Description
						</label>
						<textarea
							value={description}
							onChange={(e) => setDescription(e.target.value)}
							rows={3}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
							placeholder="A short description of this assistant"
						/>
					</div>

					{!isNpc && <div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Opening Message
						</label>
						<textarea
							value={openingMessage}
							onChange={(e) => setOpeningMessage(e.target.value)}
							rows={3}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
							placeholder="First message when a new conversation starts"
						/>
					</div>}

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

					{!isNpc && <div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Mode
						</label>
						<select
							value={assistantMode}
							onChange={(e) => setAssistantMode(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
						>
							<option value="assistant">Assistant</option>
							<option value="agent">Agent</option>
						</select>
					</div>}

					{!isNpc && <div>
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
					</div>}

					{portraitType === 'avatar3d' && (
						<div className="space-y-1">
							<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
								VRM File {isNpc ? <span className="text-danger">*</span> : <span className="text-fg-3 normal-case">(optional, can upload later)</span>}
							</label>
							<input
								type="file"
								accept=".vrm"
								onChange={(e) => setPendingVrmFile(e.target.files?.[0] ?? null)}
								className="w-full text-sm text-accent file:mr-3 file:border file:border-line-1 file:bg-bg-1 file:text-fg-3 file:text-[0.65rem] file:tracking-[0.1em] file:px-3 file:py-1 file:cursor-pointer"
							/>
							{pendingVrmFile && (
								<span className="text-fg-3 text-[0.65rem]">{pendingVrmFile.name}</span>
							)}
						</div>
					)}
				</div>

				{portraitType === 'image' && (
					<>
						{/* Divider */}
						<div className="border-t border-line-1" />

						{/* Default image */}
						<div className="space-y-2">
							<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
								Default Image <span className="text-danger">*</span>
							</label>
							<div
								onClick={() => defaultImageRef.current?.click()}
								className="w-32 h-32 border border-dashed border-line-1 flex items-center justify-center cursor-pointer hover:border-accent/50 transition-colors overflow-hidden"
							>
								{defaultPreview ? (
									<img src={defaultPreview} alt="Default" className="w-full h-full object-cover object-top" />
								) : (
									<span className="text-fg-3 text-[0.65rem] tracking-[0.1em] text-center px-2">
										CLICK TO SELECT
									</span>
								)}
							</div>
							<input
								ref={defaultImageRef}
								type="file"
								accept="image/*"
								onChange={handleDefaultImage}
								className="hidden"
							/>
						</div>

						{/* Emotions */}
						<EmotionGrid
							emotions={stagedEmotions}
							onAdd={handleAddEmotion}
							onDelete={handleDeleteEmotion}
							onUpdateImage={handleReplaceImage}
						/>

						{/* Restricted Emotions */}
						<EmotionGrid
							label="Restricted Emotions"
							emotions={stagedRestrictedEmotions}
							onAdd={handleAddRestrictedEmotion}
							onDelete={handleDeleteRestrictedEmotion}
							onUpdateImage={handleReplaceRestrictedImage}
						/>
					</>
				)}

				{portraitType === 'avatar3d' && (
					<>
						{/* Divider */}
						<div className="border-t border-line-1" />

						<DefaultPoseEditor
							pose={stagedPoses.find((p) => p.name === 'default') || { name: 'default', vrm_blendshapes: null, animation_url: null, animation_original_name: null }}
							onUpdateBlendshapes={handleUpdateDefaultPoseBlendshapes}
							onUploadAnimation={handleUploadDefaultPoseAnimation}
							onDeleteAnimation={handleDeleteDefaultPoseAnimation}
						/>

						<PoseEditor
							poses={stagedPoses.filter((p) => p.name !== 'default')}
							onAdd={handleAddPose}
							onDelete={handleDeletePose}
							onUpdateBlendshapes={handleUpdatePoseBlendshapes}
							onUploadAnimation={handleUploadPoseAnimation}
							onDeleteAnimation={handleDeletePoseAnimation}
						/>
					</>
				)}

				{/* Divider */}
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
									onClick={() => setPromptMode(mode)}
									className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border transition-colors cursor-pointer ${
										promptMode === mode
											? 'border-accent text-accent bg-accent/10'
											: 'border-line-1 text-fg-3 hover:border-fg-3'
									}`}
								>
									{mode === 'manual' ? 'Manual' : 'Paste JSON'}
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
								rows={12}
								className={`w-full bg-bg-1 border text-accent text-sm px-3 py-2 outline-none transition-colors resize-y font-mono ${
									promptJsonError ? 'border-danger' : 'border-line-1 focus:border-accent/50'
								}`}
								placeholder='[{"role": "system", "content": "..."}]'
							/>
							{promptJsonError && (
								<p className="text-danger text-[0.65rem] mt-1">{promptJsonError}</p>
							)}
						</div>
					)}
				</div>

				{/* Submit */}
				<div className="flex justify-end pt-2 pb-4">
					<button
						onClick={handleSubmit}
						disabled={isSaving}
						className={`text-[0.75rem] tracking-[0.1em] px-6 py-2 transition-colors ${
							isSaving
								? 'bg-bg-3 text-fg-3 cursor-default'
								: 'button-success cursor-pointer'
						}`}
					>
						{isSaving ? 'CREATING...' : `CREATE ${isNpc ? 'NPC' : 'ASSISTANT'}`}
					</button>
				</div>
			</div>
		</>
	);
}
