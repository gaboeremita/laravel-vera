import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import Header from '../components/Header.jsx';
import PromptNode from '../components/PromptNode.jsx';
import ConfirmationModal from '../components/common/ConfirmationModal.jsx';
import usePrompt from '../hooks/usePrompt.js';

export default function PromptPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();

	const {
		sections,
		isLoading,
		isSaving,
		setValueAtPath,
		addKey,
		removeKey,
		renameKey,
		addListItem,
		removeListItem,
		updateListItem,
		save,
		destroy,
	} = usePrompt(1, addToast);

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
		await destroy();
		setShowDeletePrompt(false);
	};

	if (isLoading) {
		return (
			<>
				<Header
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					actions={
						<button onClick={() => navigate(-1)} className="button-primary">
							← PREVIOUS PAGE
						</button>
					}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Prompt</span>
				</Header>
				<div className="flex-1 p-5">
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				</div>
			</>
		);
	}

	const entries = sections ? Object.entries(sections) : [];

	return (
		<>
			<Header
				status={{ label: isSaving ? 'SAVING' : 'WAITING', color: isSaving ? 'text-warning' : 'text-info', dot: '●', blink: isSaving }}
				counter={sections ? `SECTIONS: ${entries.length}` : null}
				actions={
					<div className="flex items-center gap-3">
						<button onClick={() => navigate(-1)} className="button-primary">
							← PREVIOUS PAGE
						</button>
						{sections && (
							<button
								onClick={() => setShowDeletePrompt(true)}
								className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger transition-colors"
							>
								DELETE PROMPT
							</button>
						)}
					</div>
				}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Prompt</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-4">
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
			</div>

			{/* Section delete confirmation */}
			{deleteTarget && (
				<ConfirmationModal
					message={`Delete section "${deleteTarget[deleteTarget.length - 1]}"?`}
					onConfirm={handleConfirmDelete}
					onCancel={() => setDeleteTarget(null)}
				/>
			)}

			{/* Full prompt delete confirmation */}
			{showDeletePrompt && (
				<ConfirmationModal
					message="Delete the entire prompt? This cannot be undone."
					onConfirm={handleDestroyPrompt}
					onCancel={() => setShowDeletePrompt(false)}
				/>
			)}
		</>
	);
}