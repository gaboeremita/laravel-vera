import { useState } from 'react';
import { AnimatePresence } from 'framer-motion';
import PromptNode from './PromptNode.jsx';
import ConfirmationModal from './common/ConfirmationModal.jsx';

/**
 * Reusable prompt tree editor.
 * Used by both the standalone PromptPage and the assistant create/edit pages.
 *
 * @param {object} props
 * @param {object|null} props.sections - Current prompt tree state
 * @param {boolean} props.isSaving
 * @param {function} props.setValueAtPath
 * @param {function} props.addKey
 * @param {function} props.removeKey
 * @param {function} props.renameKey
 * @param {function} props.addListItem
 * @param {function} props.removeListItem
 * @param {function} props.updateListItem
 * @param {function} props.save
 * @param {function|null} props.destroy - Omit to hide the delete button
 */
export default function PromptEditor({
										 sections,
										 isSaving,
										 setValueAtPath,
										 addKey,
										 removeKey,
										 renameKey,
										 addListItem,
										 removeListItem,
										 updateListItem,
										 save,
										 destroy = null,
									 }) {
	const [deleteTarget, setDeleteTarget] = useState(null);
	const [isAdding, setIsAdding] = useState(false);
	const [newKeyName, setNewKeyName] = useState('');
	const [newKeyType, setNewKeyType] = useState('string');
	const [showDeletePrompt, setShowDeletePrompt] = useState(false);

	const handleAddSection = () => {
		const trimmed = newKeyName.trim();
		if (!trimmed) return;

		addKey([], trimmed, newKeyType);
		setNewKeyName('');
		setNewKeyType('string');
		setIsAdding(false);
	};

	const handleCancelAdd = () => {
		setNewKeyName('');
		setNewKeyType('string');
		setIsAdding(false);
	};

	const handleConfirmDelete = () => {
		if (deleteTarget) {
			removeKey(deleteTarget);
			setDeleteTarget(null);
		}
	};

	const handleDestroyPrompt = async () => {
		if (destroy) {
			await destroy();
			setShowDeletePrompt(false);
		}
	};

	const entries = sections ? Object.entries(sections) : [];

	return (
		<div className="space-y-4">
			{/* No prompt state */}
			{!sections && !isAdding && (
				<div className="text-fg-3 text-sm text-center py-8">
					No prompt configured. Add a section to get started.
				</div>
			)}

			{/* Sections */}
			<AnimatePresence initial={false}>
				{entries.map(([key, value]) => (
					<PromptNode
						key={key}
						path={[key]}
						label={key}
						value={value}
						depth={0}
						onUpdateValue={setValueAtPath}
						onAddKey={addKey}
						onRemoveKey={removeKey}
						onRenameKey={renameKey}
						onAddListItem={addListItem}
						onRemoveListItem={removeListItem}
						onUpdateListItem={updateListItem}
						onRequestDelete={() => setDeleteTarget([key])}
					/>
				))}
			</AnimatePresence>

			{/* Add section */}
			{!isAdding ? (
				<button
					onClick={() => setIsAdding(true)}
					className="w-full text-[0.75rem] tracking-[0.1em] py-3 transition-colors border border-dashed border-line-1 text-success cursor-pointer hover:border-success/50 hover:bg-bg-1"
				>
					+ ADD SECTION
				</button>
			) : (
				<div className="border border-line-1 p-4 space-y-3">
					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Section Name
						</label>
						<input
							type="text"
							value={newKeyName}
							onChange={(e) => setNewKeyName(e.target.value)}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
							placeholder="e.g. personality"
							autoFocus
						/>
					</div>

					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							Type
						</label>
						<div className="flex gap-2">
							{['string', 'list', 'object'].map((type) => (
								<button
									key={type}
									onClick={() => setNewKeyType(type)}
									className={`px-3 py-1 text-[0.7rem] tracking-[0.1em] border transition-colors cursor-pointer ${
										newKeyType === type
											? 'border-accent text-accent bg-accent/10'
											: 'border-line-1 text-fg-3 hover:border-fg-3'
									}`}
								>
									{type.toUpperCase()}
								</button>
							))}
						</div>
					</div>

					<div className="flex justify-end gap-2">
						<button
							onClick={handleCancelAdd}
							className="px-4 py-1.5 text-[0.7rem] tracking-[0.1em] border border-line-1 text-fg-3 cursor-pointer hover:text-fg-1 transition-colors"
						>
							CANCEL
						</button>
						<button
							onClick={handleAddSection}
							disabled={!newKeyName.trim()}
							className={`px-4 py-1.5 text-[0.7rem] tracking-[0.1em] transition-colors ${
								newKeyName.trim()
									? 'button-success cursor-pointer'
									: 'bg-bg-3 text-fg-3 cursor-default'
							}`}
						>
							ADD
						</button>
					</div>
				</div>
			)}

			{/* Save button */}
			{sections && (
				<div className="flex justify-end pt-2">
					<button
						onClick={save}
						disabled={isSaving}
						className={`text-[0.75rem] tracking-[0.1em] px-6 py-2 transition-colors ${
							isSaving
								? 'bg-bg-3 text-fg-3 cursor-default'
								: 'button-success cursor-pointer'
						}`}
					>
						{isSaving ? 'SAVING...' : 'SAVE PROMPT'}
					</button>
				</div>
			)}

			{/* Section delete confirmation */}
			{deleteTarget && (
				<ConfirmationModal
					message={`Delete section "${deleteTarget[deleteTarget.length - 1]}"?`}
					onConfirm={handleConfirmDelete}
					onCancel={() => setDeleteTarget(null)}
				/>
			)}

			{/* Full prompt delete confirmation */}
			{destroy && showDeletePrompt && (
				<ConfirmationModal
					message="Delete the entire prompt? This cannot be undone."
					onConfirm={handleDestroyPrompt}
					onCancel={() => setShowDeletePrompt(false)}
				/>
			)}
		</div>
	);
}