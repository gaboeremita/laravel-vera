import { useState, useEffect } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import { AnimatePresence } from 'framer-motion';
import EntryAccordion from "../components/EntryAccordion.jsx";
import ConfirmationModal from "../components/common/ConfirmationModal.jsx";

export default function ArchivePage() {
	const navigate = useNavigate();
	const { addToast, assistantId, archiveId } = useOutletContext();

	const [name, setName] = useState('');
	const [description, setDescription] = useState('');
	const [entries, setEntries] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);
	const [isExporting, setIsExporting] = useState(false);
	const [deleteIndex, setDeleteIndex] = useState(null);
	const allCollapsed = entries.every((e) => e.collapsed);

	useEffect(() => {
		if (!archiveId) {
			setIsLoading(false);
			return;
		}

		const load = async () => {
			try {
				const res = await api.get(route('archives.show', { id: archiveId }));

				if (!res.ok) throw new Error('Failed to load archive');

				const data = await res.json();
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
			} catch(e) {
				console.log(e);
				addToast('Failed to load archive', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		load();
	}, [archiveId]);

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

			const res = await api.post(route('archives.save', { id: archiveId }), payload);

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			const data = await res.json();

			setEntries(data.entries.map((entry) => ({
				id: entry.id,
				title: entry.title,
				content: entry.content,
				keywords: (entry.keywords ?? []).join(', '),
				tags: (entry.tags ?? []).map((t) => t.name).join(', '),
				collapsed: true,
			})));

			addToast('Archive saved', 'success');
		} catch (error) {
			addToast(error.message || 'Failed to save archive', 'error');
		} finally {
			setIsSaving(false);
		}
	};

	const exportArchive = async () => {
		setIsExporting(true);

		try {
			const res = await api.get(route('archives.export', { id: archiveId }));

			if (!res.ok) throw new Error('Export failed');

			const blob = await res.blob();
			const disposition = res.headers.get('Content-Disposition') || '';
			const match = disposition.match(/filename="?([^"]+)"?/);
			const filename = match ? match[1] : `${name || 'archive'}.md`;

			const url = window.URL.createObjectURL(blob);
			const link = document.createElement('a');
			link.href = url;
			link.download = filename;
			document.body.appendChild(link);
			link.click();
			link.remove();
			window.URL.revokeObjectURL(url);
		} catch (error) {
			addToast(error.message || 'Failed to export archive', 'error');
		} finally {
			setIsExporting(false);
		}
	};

	const headerActions = (
		<button onClick={() => navigate(-1)} className="button-primary">
			← PREVIOUS PAGE
		</button>
	);

	if (isLoading) {
		return (
			<>
				<Header
					settingsPath={`/assistants/${assistantId}/settings`}
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					actions={headerActions}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Archive</span>
				</Header>
				<div className="flex-1 p-5">
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				</div>
			</>
		);
	}

	if (!archiveId) {
		return (
			<>
				<Header
					settingsPath={`/assistants/${assistantId}/settings`}
					status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
					actions={headerActions}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Archive</span>
				</Header>
				<div className="flex-1 flex items-center justify-center p-5">
					<div className="text-center space-y-2">
						<p className="text-fg-3 text-sm tracking-[0.05em]">There's no archive for this assistant.</p>
						<p className="text-fg-3 text-[0.7rem] tracking-[0.05em]">
							Assign one from the assistant's edit page.
						</p>
					</div>
				</div>
			</>
		);
	}

	return (
		<>
			<Header
				settingsPath={`/assistants/${assistantId}/settings`}
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`ENTRIES: ${entries.length}`}
				actions={headerActions}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Archive</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-6">
				{/* Archive metadata */}
				<div className="space-y-3">
					<div>
						<label className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase block mb-1">
							Name
						</label>
						<div className="flex gap-3">
							<input
								type="text"
								value={name}
								onChange={(e) => setName(e.target.value)}
								maxLength={100}
								className="flex-1 bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
								placeholder="e.g. The Bridge Universe"
							/>
							<button
								onClick={exportArchive}
								disabled={isExporting}
								className="button-primary shrink-0"
							>
								{isExporting ? 'EXPORTING...' : 'EXPORT ARCHIVE'}
							</button>
						</div>
					</div>
					<div>
						<label className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase block mb-1">
							Description
						</label>
						<textarea
							value={description}
							onChange={(e) => setDescription(e.target.value)}
							rows={3}
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
							placeholder="Describe this archive..."
						/>
					</div>
				</div>

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
							className="w-full border border-dashed border-line-1 text-success text-[0.75rem] tracking-[0.1em] cursor-pointer hover:border-success/50 hover:bg-green-400/5 transition-colors py-3 mt-4"
						>
							+ ADD ENTRY
						</button>
					</AnimatePresence>
				</div>
			</div>

			{/* Save button */}
			<div className="px-5 py-3 border-t border-line-1 shrink-0">
				<button
					onClick={save}
					disabled={isSaving || !name.trim() || !description.trim()}
					className={`w-full button-success ${
						isSaving || !name.trim() || !description.trim()
							? 'bg-[#1a1a2e] text-fg-3 cursor-default'
							: 'bg-accent/10 border border-accent text-accent hover:bg-accent/20 cursor-pointer'
					}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE ARCHIVE'}
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
