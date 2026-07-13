import { useState, useRef } from 'react';
import ConfirmationModal from './common/ConfirmationModal.jsx';

/**
 * Reusable emotion management grid.
 *
 * @param {Array} emotions - [{id, name, image_url, preview}]
 * @param {function} onAdd - (name, file) => void
 * @param {function} onDelete - (emotion) => void
 * @param {function} onUpdateImage - (emotion, file) => void
 * @param {boolean} isSaving
 */
export default function EmotionGrid({ emotions, onAdd, onDelete, onUpdateImage, isSaving = false }) {
	const [isAdding, setIsAdding] = useState(false);
	const [newName, setNewName] = useState('');
	const [newFile, setNewFile] = useState(null);
	const [newPreview, setNewPreview] = useState(null);
	const [deleteTarget, setDeleteTarget] = useState(null);
	const fileInputRef = useRef(null);
	const replaceInputRef = useRef(null);
	const [replaceTarget, setReplaceTarget] = useState(null);

	const handleFileSelect = (e) => {
		const file = e.target.files?.[0];
		if (!file) return;

		setNewFile(file);
		const reader = new FileReader();
		reader.onload = () => setNewPreview(reader.result);
		reader.readAsDataURL(file);
	};

	const handleAdd = () => {
		const trimmed = newName.trim();
		if (!trimmed || !newFile) return;

		onAdd(trimmed, newFile);
		setNewName('');
		setNewFile(null);
		setNewPreview(null);
		setIsAdding(false);
	};

	const handleCancelAdd = () => {
		setNewName('');
		setNewFile(null);
		setNewPreview(null);
		setIsAdding(false);
	};

	const handleConfirmDelete = () => {
		if (deleteTarget) {
			onDelete(deleteTarget);
			setDeleteTarget(null);
		}
	};

	const handleReplaceFile = (e) => {
		const file = e.target.files?.[0];
		if (!file || !replaceTarget) return;

		onUpdateImage(replaceTarget, file);
		setReplaceTarget(null);
	};

	return (
		<div className="space-y-3">
			<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
				Emotions ({emotions.length})
			</label>

			<div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
				{emotions.map((emotion, index) => (
					<div
						key={emotion.id ?? `staged-${index}`}
						className="border border-line-1 bg-bg-1 overflow-hidden group"
					>
						{/* Image */}
						<div
							className="aspect-square overflow-hidden cursor-pointer relative"
							onClick={() => {
								setReplaceTarget(emotion);
								replaceInputRef.current?.click();
							}}
						>
							<img
								src={emotion.preview || emotion.image_url}
								alt={emotion.name}
								className="w-full h-full object-cover"
							/>
							<div className="absolute inset-0 bg-bg-0/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
								<span className="text-fg-1 text-[0.65rem] tracking-[0.1em]">REPLACE</span>
							</div>
						</div>

						{/* Name and delete */}
						<div className="p-2 flex items-center justify-between">
							<span className="text-accent text-[0.7rem] tracking-[0.05em] truncate">
								{emotion.name}
							</span>
							{emotion.name !== 'default' && (
								<button
									onClick={() => setDeleteTarget(emotion)}
									className="text-danger text-[0.65rem] cursor-pointer hover:text-danger transition-colors shrink-0 ml-2"
								>
									✕
								</button>
							)}
						</div>
					</div>
				))}

				{/* Add emotion card */}
				{!isAdding ? (
					<button
						onClick={() => setIsAdding(true)}
						className="aspect-square border border-dashed border-line-1 flex items-center justify-center text-success text-[0.7rem] tracking-[0.1em] cursor-pointer hover:border-success/50 hover:bg-bg-1 transition-colors"
					>
						+ ADD
					</button>
				) : (
					<div className="border border-line-1 bg-bg-1 p-3 space-y-2 col-span-2">
						{/* Preview / file picker */}
						<div
							onClick={() => fileInputRef.current?.click()}
							className="aspect-video border border-dashed border-line-1 flex items-center justify-center cursor-pointer hover:border-accent/50 transition-colors overflow-hidden"
						>
							{newPreview ? (
								<img src={newPreview} alt="Preview" className="w-full h-full object-cover" />
							) : (
								<span className="text-fg-3 text-[0.65rem] tracking-[0.1em]">
									CLICK TO SELECT IMAGE
								</span>
							)}
						</div>

						<input
							ref={fileInputRef}
							type="file"
							accept="image/*"
							onChange={handleFileSelect}
							className="hidden"
						/>

						{/* Name input */}
						<input
							type="text"
							value={newName}
							onChange={(e) => setNewName(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
							placeholder="e.g. annoyed"
							autoFocus
						/>

						{/* Actions */}
						<div className="flex justify-end gap-2">
							<button
								onClick={handleCancelAdd}
								className="px-3 py-1 text-[0.65rem] tracking-[0.1em] border border-line-1 text-fg-3 cursor-pointer hover:text-fg-1 transition-colors"
							>
								CANCEL
							</button>
							<button
								onClick={handleAdd}
								disabled={!newName.trim() || !newFile}
								className={`px-3 py-1 text-[0.65rem] tracking-[0.1em] transition-colors ${
									newName.trim() && newFile
										? 'button-success cursor-pointer'
										: 'bg-bg-3 text-fg-3 cursor-default'
								}`}
							>
								ADD
							</button>
						</div>
					</div>
				)}
			</div>

			{/* Hidden input for replacing images */}
			<input
				ref={replaceInputRef}
				type="file"
				accept="image/*"
				onChange={handleReplaceFile}
				className="hidden"
			/>

			{deleteTarget && (
				<ConfirmationModal
					message={`Delete emotion "${deleteTarget.name}"?`}
					onConfirm={handleConfirmDelete}
					onCancel={() => setDeleteTarget(null)}
				/>
			)}
		</div>
	);
}