import { useState, useRef, useEffect } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import PromptEditor from '../components/PromptEditor.jsx';
import EmotionGrid from '../components/EmotionGrid.jsx';
import useLocalPrompt from '../hooks/useLocalPrompt.js';

export default function CreateAssistantPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();

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

	// Archive
	const [archives, setArchives] = useState([]);
	const [selectedArchiveId, setSelectedArchiveId] = useState('');

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

	const handleSubmit = async () => {
		if (!name.trim() || !slug.trim()) {
			addToast('Name and slug are required', 'error');
			return;
		}

		if (!defaultImage) {
			addToast('A default image is required', 'error');
			return;
		}

		setIsSaving(true);

		try {
			const formData = new FormData();
			formData.append('name', name.trim());
			formData.append('slug', slug.trim());
			formData.append('description', description.trim());
			formData.append('opening_message', openingMessage.trim());

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

		formData.append('emotions[0][name]', 'default');
			formData.append('emotions[0][image]', defaultImage);

			stagedEmotions.forEach((emotion, i) => {
				formData.append(`emotions[${i + 1}][name]`, emotion.name);
				formData.append(`emotions[${i + 1}][image]`, emotion.file);
			});

			const res = await api.postForm(route('assistants.store'), formData);

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Failed to create assistant');
			}

			addToast('Assistant created', 'success');
			navigate('/assistants');
		} catch (e) {
			addToast(e.message || 'Failed to create assistant', 'error');
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
				actions={
					<button onClick={() => navigate('/assistants')} className="button-primary">
						← BACK
					</button>
				}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">New Assistant</span>
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

					<div>
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
							placeholder="A short description of this assistant"
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
							placeholder="First message when a new conversation starts"
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
				</div>

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
						{isSaving ? 'CREATING...' : 'CREATE ASSISTANT'}
					</button>
				</div>
			</div>
		</>
	);
}