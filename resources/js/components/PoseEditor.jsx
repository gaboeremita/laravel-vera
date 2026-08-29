import { useId, useRef, useState } from 'react';
import ConfirmationModal from './common/ConfirmationModal.jsx';
import { BlendshapeRows, EXPRESSION_SUGGESTIONS } from './VrmEmotionEditor.jsx';

function AnimationFileControl({ pose, onUploadAnimation, onDeleteAnimation }) {
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
					message={`Delete the animation file for "${pose.name}"?`}
					onConfirm={handleConfirmDelete}
					onCancel={() => setConfirmingDelete(false)}
				/>
			)}
		</div>
	);
}

function PoseRow({ pose, onSave, onDelete, onUploadAnimation, onDeleteAnimation, datalistId }) {
	const [draft, setDraft] = useState(() => (pose.vrm_blendshapes || []).map((b) => (b.weight <= 1 ? { ...b, weight: Math.round(b.weight * 100) } : b)));
	const [syncedPose, setSyncedPose] = useState(pose);
	const [isSaving, setIsSaving] = useState(false);

	if (syncedPose !== pose) {
		setSyncedPose(pose);
		setDraft((pose.vrm_blendshapes || []).map((b) => (b.weight <= 1 ? { ...b, weight: Math.round(b.weight * 100) } : b)));
	}

	const handleSave = async () => {
		setIsSaving(true);
		try {
			await onSave(draft.filter((b) => b.expression.trim()));
		} finally {
			setIsSaving(false);
		}
	};

	return (
		<div className="border border-line-1 bg-bg-1 p-3 space-y-2">
			<div className="flex items-center justify-between">
				<span className="text-accent text-[0.7rem] tracking-[0.05em]">{pose.name}</span>
				<button
					onClick={onDelete}
					className="text-danger text-[0.65rem] cursor-pointer hover:text-danger transition-colors"
				>
					✕ DELETE
				</button>
			</div>
			<BlendshapeRows blendshapes={draft} onChange={setDraft} datalistId={datalistId} />
			<div className="flex justify-end">
				<button
					onClick={handleSave}
					disabled={isSaving}
					className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${
						isSaving ? 'bg-bg-3 text-fg-3 cursor-default' : 'button-success cursor-pointer'
					}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE'}
				</button>
			</div>
			<AnimationFileControl pose={pose} onUploadAnimation={onUploadAnimation} onDeleteAnimation={onDeleteAnimation} />
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
 * @param {function} onUpdateBlendshapes - (pose, blendshapes) => void
 * @param {function} onUploadAnimation - (pose, file) => void
 * @param {function} onDeleteAnimation - (pose) => void
 */
export default function PoseEditor({ poses, onAdd, onDelete, onUpdateBlendshapes, onUploadAnimation, onDeleteAnimation, label = 'Poses' }) {
	const datalistId = useId();
	const [isAdding, setIsAdding] = useState(false);
	const [newName, setNewName] = useState('');
	const [newBlendshapes, setNewBlendshapes] = useState([{ expression: '', weight: 100 }]);
	const [deleteTarget, setDeleteTarget] = useState(null);

	const handleAdd = () => {
		const trimmed = newName.trim();
		if (!trimmed) return;

		onAdd(trimmed, newBlendshapes.filter((b) => b.expression.trim()));
		setNewName('');
		setNewBlendshapes([{ expression: '', weight: 100 }]);
		setIsAdding(false);
	};

	const handleCancelAdd = () => {
		setNewName('');
		setNewBlendshapes([{ expression: '', weight: 100 }]);
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
						onSave={(blendshapes) => onUpdateBlendshapes(pose, blendshapes)}
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
						<div className="flex justify-end gap-2">
							<button
								onClick={handleCancelAdd}
								className="px-3 py-1 text-[0.65rem] tracking-[0.1em] border border-line-1 text-fg-3 cursor-pointer hover:text-fg-1 transition-colors"
							>
								CANCEL
							</button>
							<button
								onClick={handleAdd}
								disabled={!newName.trim()}
								className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${
									newName.trim() ? 'button-success cursor-pointer' : 'bg-bg-3 text-fg-3 cursor-default'
								}`}
							>
								ADD
							</button>
						</div>
					</div>
				)}
			</div>

			{deleteTarget && (
				<ConfirmationModal
					message={`Delete pose "${deleteTarget.name}"?`}
					onConfirm={handleConfirmDelete}
					onCancel={() => setDeleteTarget(null)}
				/>
			)}
		</div>
	);
}
