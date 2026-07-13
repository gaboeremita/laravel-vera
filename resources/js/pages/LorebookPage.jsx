import { useState, useEffect } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import { AnimatePresence } from 'framer-motion';
import EntryAccordion from "../components/EntryAccordion.jsx";
import ConfirmationModal from "../components/common/ConfirmationModal.jsx";

export default function LorebookPage() {
	const navigate = useNavigate();
	const { addToast } = useOutletContext();

	const [name, setName] = useState('');
	const [description, setDescription] = useState('');
	const [entries, setEntries] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);
	const [deleteIndex, setDeleteIndex] = useState(null);
	const allCollapsed = entries.every((e) => e.collapsed);

	useEffect(() => {
		const load = async () => {
			try {
				const res = await api.get(route('lorebook.show'));

				// if (!res.ok) {
				// 	throw new Error('Failed to load lorebook');
				// }

				const data = await res.json();
				if (data && Object.keys(data).length > 0) {
					setName(data.name ?? '');
					setDescription(data.description ?? '');
					setEntries(data.entries.map((entry) => ({
						id: entry.id,
						title: entry.title,
						content: entry.content,
						keywords: (entry.keywords ?? []).join(', '),
						tags: (entry.tags ?? []).map((t) => t.name).join(', '),
						collapsed: true,
					})));
				}
			} catch(e) {
				console.log(e);
				addToast('Failed to load lorebook', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		load();
	}, []);

	const addEntry = () => {
		setEntries((prev) => [
			...prev,
			{ uid: crypto.randomUUID(), title: '', content: '', keywords: '', tags: '', collapsed: false },
		]);
	};

	const removeEntry = (index) => {
		setEntries((prev) => prev.filter((_, i) => i !== index));
	};

	const updateEntry = (index, field, value) => {
		setEntries((prev) =>
			prev.map((entry, i) =>
				i === index ? { ...entry, [field]: value } : entry,
			),
		);
	};

	const save = async () => {
		setIsSaving(true);

		try {
			const payload = {
				name,
				description,
				entries: entries.map((entry) => ({
					...(entry.id && { id: entry.id }),
					title: entry.title,
					content: entry.content,
					keywords: entry.keywords
						? entry.keywords.split(',').map((k) => k.trim()).filter(Boolean)
						: [],
					tags: entry.tags
						? entry.tags.split(',').map((t) => t.trim()).filter(Boolean)
						: [],
				})),
			};

			const res = await api.post(route('lorebook.save'), payload);

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			const data = await res.json();

			// Sync IDs back from server response
			setEntries(data.entries.map((entry) => ({
				id: entry.id,
				title: entry.title,
				content: entry.content,
				keywords: (entry.keywords ?? []).join(', '),
				tags: (entry.tags ?? []).map((t) => t.name).join(', '),
				collapsed: true,
			})));

			addToast('Lorebook saved', 'success');
		} catch (error) {
			addToast(error.message || 'Failed to save lorebook', 'error');
		} finally {
			setIsSaving(false);
		}
	};

	if (isLoading) {
		return (
			<>
				<Header settingsPath={`/assistants/${assistantId}/settings`}
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					actions={
						<button
							onClick={() => navigate(`/assistants/${assistantId}/conversations`)}
							className="bg-accent-3/15 border border-accent-3 text-accent-3 hover:bg-accent-3/25 text-[0.75rem] tracking-[0.1em]  cursor-pointer transition-colors px-4 py-1.5"
						>
							← CONVERSATIONS
						</button>
					}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Lorebook</span>
				</Header>
				<div className="flex-1 p-5">
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				</div>
			</>
		);
	}

	return (
		<>
			<Header settingsPath={`/assistants/${assistantId}/settings`}
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`ENTRIES: ${entries.length}`}
				actions={
					<button
						onClick={() => navigate(-1)}
						className="button-primary"
					>
						← PREVIOUS PAGE
					</button>
				}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Lorebook</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-6">
				{/* Lorebook metadata */}
				<div className="space-y-3">
					<div>
						<label className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase block mb-1">
							Name
						</label>
						<input
							type="text"
							value={name}
							onChange={(e) => setName(e.target.value)}
							maxLength={100}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm  px-3 py-2 outline-none focus:border-accent/50 transition-colors"
							placeholder="e.g. The Bridge Universe"
						/>
					</div>
					<div>
						<label className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase block mb-1">
							Description
						</label>
						<textarea
							value={description}
							onChange={(e) => setDescription(e.target.value)}
							rows={3}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm  px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
							placeholder="Describe this lorebook..."
						/>
					</div>
				</div>

				{/* Divider */}
				<hr className="border-t border-line-1" />

				{/* Entries */}
				<div>
					<div className="flex items-center justify-between mb-3">
						<span className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase">
							Entries ({entries.length})
						</span>
						{entries.length > 0 && (
							<button
								onClick={() => {
									const newState = !allCollapsed;
									setEntries((prev) => prev.map((e) => ({ ...e, collapsed: newState })));
								}}
								className="button-primary"
							>
								{allCollapsed ? 'EXPAND ALL' : 'COLLAPSE ALL'}
							</button>
						)}
					</div>

					<AnimatePresence initial={false}>
						{entries.map((entry, index) => (
							<EntryAccordion
								key={entry.id ?? entry.uid}
								entry={entry}
								index={index}
								onUpdate={(field, value) => updateEntry(index, field, value)}
								onDelete={() => setDeleteIndex(index)}
							/>
						))}

						{entries.length === 0 && (
							<div className="text-fg-3 text-sm text-center py-8">
								No entries yet.
							</div>
						)}

						<button
							onClick={addEntry}
							className="w-full border border-dashed border-line-1 text-success text-[0.75rem] tracking-[0.1em]  cursor-pointer hover:border-success/50 hover:bg-green-400/5 transition-colors py-3 mt-4"
						>
							+ ADD ENTRY
						</button>
					</AnimatePresence>
				</div>
			</div>

			{/* Save button — fixed at bottom */}
			<div className="px-5 py-3 border-t border-line-1 shrink-0">
				<button
					onClick={save}
					disabled={isSaving || !name.trim() || !description.trim()}
					className={`w-full  button-success ${
						isSaving || !name.trim() || !description.trim()
							? 'bg-[#1a1a2e] text-fg-3 cursor-default'
							: 'bg-accent/10 border border-accent text-accent hover:bg-accent/20 cursor-pointer'
					}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE LOREBOOK'}
				</button>
			</div>
			{deleteIndex !== null && (
				<ConfirmationModal
					title="Delete entry"
					message={`Delete entry "${entries[deleteIndex]?.title || `Entry ${deleteIndex + 1}`}"?`}
					options={[
						{ label: 'DELETE', value: 'confirm', destructive: true },
						{ label: 'CANCEL', value: 'cancel', cancel: true },
					]}
					onSelect={(value) => {
						if (value === 'confirm') {
							removeEntry(deleteIndex);
						}
						setDeleteIndex(null);
					}}
				/>
			)}
		</>
	);
}