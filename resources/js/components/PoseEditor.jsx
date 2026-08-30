import { useId, useRef, useState } from 'react';
import ConfirmationModal from './common/ConfirmationModal.jsx';
import { BlendshapeRows, EXPRESSION_SUGGESTIONS } from './VrmEmotionEditor.jsx';

export function AnimationFileControl({ pose, onUploadAnimation, onDeleteAnimation }) {
	const inputRef = useRef(null);
	const [isUploading, setIsUploading] = useState(false);
	const [isDeleting, setIsDeleting] = useState(false);
	const [confirmingDelete, setConfirmingDelete] = useState(false);

	const filename = pose.animation_original_name || (pose.animation_url ? decodeURIComponent(pose.animation_url.split('/').pop()) : null);

	const handleUpload = async (e) => {
		const file = e.target.files?.[0];
		if (!file) return;
		setIsUploading(true);
		try {
			await onUploadAnimation(pose, file);
		} finally {
			setIsUploading(false);
			if (inputRef.current) inputRef.current.value = '';
		}
	};

	const handleConfirmDelete = async () => {
		setConfirmingDelete(false);
		setIsDeleting(true);
		try {
			await onDeleteAnimation(pose);
		} finally {
			setIsDeleting(false);
		}
	};

	return (
		<div className="space-y-1.5">
			<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">Animation File (.vrma / .fbx)</label>
			{pose.animation_url ? (
				<div className="flex items-center gap-2">
					<span className="text-accent text-xs truncate flex-1">{filename}</span>
					<button
						onClick={() => setConfirmingDelete(true)}
						disabled={isDeleting}
						className="text-[0.65rem] tracking-[0.1em] px-3 py-1 border border-danger text-danger hover:bg-danger/10 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-default"
					>
						{isDeleting ? 'DELETING...' : 'DELETE'}
					</button>
				</div>
			) : (
				<button
					onClick={() => inputRef.current?.click()}
					disabled={isUploading}
					className="text-[0.65rem] tracking-[0.1em] px-3 py-1 border border-line-1 text-fg-3 hover:border-fg-3 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-default"
				>
					{isUploading ? 'UPLOADING...' : 'UPLOAD ANIMATION'}
				</button>
			)}
			<input ref={inputRef} type="file" accept=".vrma,.fbx" onChange={handleUpload} className="hidden" />

			{confirmingDelete && (
				<ConfirmationModal
					title="Delete animation"
					message={`Delete the animation file for "${pose.name}"?`}
					options={[
						{ label: 'DELETE', value: 'confirm', destructive: true },
						{ label: 'CANCEL', value: 'cancel', cancel: true },
					]}
					onSelect={(value) => {
						if (value === 'confirm') handleConfirmDelete();
						else setConfirmingDelete(false);
					}}
				/>
			)}
		</div>
	);
}

function PoseRow({ pose, onSave, onDelete, onUploadAnimation, onDeleteAnimation, datalistId }) {
	const [expanded, setExpanded] = useState(false);
	const [draft, setDraft] = useState(() => (pose.vrm_blendshapes || []).map((b) => (b.weight <= 1 ? { ...b, weight: Math.round(b.weight * 100) } : b)));
	const [nameDraft, setNameDraft] = useState(pose.name);
	const [syncedPose, setSyncedPose] = useState(pose);
	const [isSaving, setIsSaving] = useState(false);

	if (syncedPose !== pose) {
		setSyncedPose(pose);
		setDraft((pose.vrm_blendshapes || []).map((b) => (b.weight <= 1 ? { ...b, weight: Math.round(b.weight * 100) } : b)));
		setNameDraft(pose.name);
	}

	const handleSave = async () => {
		const trimmedName = nameDraft.trim();
		if (!trimmedName) return;

		setIsSaving(true);
		try {
			await onSave(trimmedName, draft.filter((b) => b.expression.trim()));
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<div className="border border-line-1 bg-bg-1">
			<div className="w-full flex items-center gap-2 p-3">
				<button
					type="button"
					onClick={() => setExpanded((prev) => !prev)}
					aria-expanded={expanded}
					className="flex-1 min-w-0 flex items-center gap-2 cursor-pointer text-left"
				>
					<span className={`text-fg-3 text-[0.6rem] transition-transform shrink-0 ${expanded ? 'rotate-90' : ''}`}>▸</span>
					<span className="flex-1 min-w-0 truncate text-accent text-[0.7rem] tracking-[0.05em]">{pose.name}</span>
				</button>
				<button
					type="button"
					onClick={onDelete}
					className="text-danger text-[0.65rem] cursor-pointer hover:text-danger transition-colors shrink-0"
				>
					✕ DELETE
				</button>
			</div>

			{expanded && (
				<div className="p-3 pt-0 space-y-2">
					<input
						type="text"
						value={nameDraft}
						onChange={(e) => setNameDraft(e.target.value)}
						className="w-full bg-bg-0 border border-line-1 text-accent text-[0.7rem] tracking-[0.05em] px-2 py-1 outline-none focus:border-accent/50 transition-colors"
					/>
					<BlendshapeRows blendshapes={draft} onChange={setDraft} datalistId={datalistId} />
					<div className="flex justify-end">
						<button
							onClick={handleSave}
							disabled={isSaving || !nameDraft.trim()}
							className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${
								isSaving || !nameDraft.trim() ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'
							}`}
						>
							{isSaving ? 'SAVING...' : 'SAVE'}
						</button>
					</div>
					<AnimationFileControl pose={pose} onUploadAnimation={onUploadAnimation} onDeleteAnimation={onDeleteAnimation} />
				</div>
			)}
		</div>
	);
}

/**
 * Per-assistant pose editor. Each pose row holds N {expression, weight%}
 * blendshape pairs and an optional uploaded animation file — either, both,
 * or neither may be configured; they are combinable, not an exclusive
 * toggle.
 *
 * @param {Array} poses - [{id, name, vrm_blendshapes, animation_url}]
 * @param {function} onAdd - (name, blendshapes) => void
 * @param {function} onDelete - (pose) => void
 * @param {function} onUpdateBlendshapes - (pose, name, blendshapes) => void
 * @param {function} onUploadAnimation - (pose, file) => void
 * @param {function} onDeleteAnimation - (pose) => void
 */
export default function PoseEditor({ poses, onAdd, onDelete, onUpdateBlendshapes, onUploadAnimation, onDeleteAnimation, label = 'Poses' }) {
	const datalistId = useId();
	const [isAdding, setIsAdding] = useState(false);
	const [newName, setNewName] = useState('');
	const [newBlendshapes, setNewBlendshapes] = useState([{ expression: '', weight: 100 }]);
	const [newAnimationFile, setNewAnimationFile] = useState(null);
	const newFileInputRef = useRef(null);
	const [deleteTarget, setDeleteTarget] = useState(null);
	const [isAddingPose, setIsAddingPose] = useState(false);

	const handleAdd = async () => {
		const trimmed = newName.trim();
		if (!trimmed) return;

		setIsAddingPose(true);
		try {
			await onAdd(trimmed, newBlendshapes.filter((b) => b.expression.trim()), newAnimationFile);
			setNewName('');
			setNewBlendshapes([{ expression: '', weight: 100 }]);
			setNewAnimationFile(null);
			setIsAdding(false);
		} finally {
			setIsAddingPose(false);
		}
	};

	const handleCancelAdd = () => {
		setNewName('');
		setNewBlendshapes([{ expression: '', weight: 100 }]);
		setNewAnimationFile(null);
		setIsAdding(false);
	};

	const handleConfirmDelete = () => {
		if (deleteTarget) {
			onDelete(deleteTarget);
			setDeleteTarget(null);
		}
	};

	return (
		<div className="space-y-3">
			<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
				{label} ({poses.length})
			</label>

			<datalist id={datalistId}>
				{EXPRESSION_SUGGESTIONS.map((name) => (
					<option key={name} value={name} />
				))}
			</datalist>

			<div className="space-y-2">
				{poses.map((pose) => (
					<PoseRow
						key={pose.id}
						pose={pose}
						onSave={(name, blendshapes) => onUpdateBlendshapes(pose, name, blendshapes)}
						onDelete={() => setDeleteTarget(pose)}
						onUploadAnimation={onUploadAnimation}
						onDeleteAnimation={onDeleteAnimation}
						datalistId={datalistId}
					/>
				))}

				{!isAdding ? (
					<button
						onClick={() => setIsAdding(true)}
						className="w-full border border-dashed border-line-1 py-2 flex items-center justify-center text-success text-[0.7rem] tracking-[0.1em] cursor-pointer hover:border-success/50 hover:bg-bg-1 transition-colors"
					>
						+ ADD POSE
					</button>
				) : (
					<div className="border border-line-1 bg-bg-1 p-3 space-y-2">
						<input
							type="text"
							value={newName}
							onChange={(e) => setNewName(e.target.value)}
							className="w-full bg-bg-0 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
							placeholder="e.g. spin"
							autoFocus
						/>
						<BlendshapeRows blendshapes={newBlendshapes} onChange={setNewBlendshapes} datalistId={datalistId} />
						<div className="space-y-1.5">
							<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">Animation File (.vrma / .fbx)</label>
							{newAnimationFile ? (
								<div className="flex items-center gap-2">
									<span className="text-accent text-xs truncate flex-1">{newAnimationFile.name}</span>
									<button
										onClick={() => {
											setNewAnimationFile(null);
											if (newFileInputRef.current) newFileInputRef.current.value = '';
										}}
										className="text-danger text-xs cursor-pointer hover:text-danger transition-colors shrink-0"
									>
										✕
									</button>
								</div>
							) : (
								<button
									onClick={() => newFileInputRef.current?.click()}
									className="text-[0.65rem] tracking-[0.1em] px-3 py-1 border border-line-1 text-fg-3 hover:border-fg-3 transition-colors cursor-pointer"
								>
									UPLOAD ANIMATION
								</button>
							)}
							<input
								ref={newFileInputRef}
								type="file"
								accept=".vrma,.fbx"
								onChange={(e) => setNewAnimationFile(e.target.files?.[0] ?? null)}
								className="hidden"
							/>
						</div>
						<div className="flex justify-end gap-2">
							<button
								onClick={handleCancelAdd}
								disabled={isAddingPose}
								className="px-3 py-1 text-[0.65rem] tracking-[0.1em] border border-line-1 text-fg-3 cursor-pointer hover:text-fg-1 transition-colors disabled:opacity-50 disabled:cursor-default"
							>
								CANCEL
							</button>
							<button
								onClick={handleAdd}
								disabled={!newName.trim() || isAddingPose}
								className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${
									newName.trim() && !isAddingPose ? 'button-success cursor-pointer' : 'bg-bg-3 text-fg-3 cursor-default'
								}`}
							>
								{isAddingPose ? 'ADDING...' : 'ADD'}
							</button>
						</div>
					</div>
				)}
			</div>

			{deleteTarget && (
				<ConfirmationModal
					title="Delete pose"
					message={`Delete pose "${deleteTarget.name}"?`}
					options={[
						{ label: 'DELETE', value: 'confirm', destructive: true },
						{ label: 'CANCEL', value: 'cancel', cancel: true },
					]}
					onSelect={(value) => {
						if (value === 'confirm') handleConfirmDelete();
						else setDeleteTarget(null);
					}}
				/>
			)}
		</div>
	);
}
