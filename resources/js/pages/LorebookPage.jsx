import { useState, useEffect } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { api } from '../utils/api.js';
import Header from '../components/Header.jsx';
import { motion, AnimatePresence } from 'framer-motion';

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
				const res = await api.get('/api/lorebook');

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

			const res = await api.post('/api/lorebook', payload);

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
				<Header
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					actions={
						<button
							onClick={() => navigate('/conversations')}
							className="bg-accent-3/15 border border-accent-3 text-accent-3 hover:bg-accent-3/25 text-[0.75rem] tracking-[0.1em] font-mono cursor-pointer transition-colors px-4 py-1.5"
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
			<Header
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`ENTRIES: ${entries.length}`}
				actions={
					<button
						onClick={() => navigate(-1)}
						className="bg-accent-3/15 border border-accent-3 text-accent-3 hover:bg-accent-3/25 text-[0.75rem] tracking-[0.1em] font-mono cursor-pointer transition-colors px-4 py-1.5"
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
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm font-mono px-3 py-2 outline-none focus:border-accent/50 transition-colors"
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
							className="w-full bg-bg-1 border border-line-1 text-accent text-sm font-mono px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
							placeholder="Describe this lorebook..."
						/>
					</div>
				</div>

				{/* Divider */}
				<div className="text-[#1a1a2e] text-[0.7rem]">
					─────────────────────────────────
				</div>

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
								className="bg-accent-3/15 border border-accent-3 text-accent-3 hover:bg-accent-3/25 text-[0.75rem] tracking-[0.1em] font-mono cursor-pointer transition-colors px-4 py-1.5"
							>
								{allCollapsed ? 'EXPAND ALL' : 'COLLAPSE ALL'}
							</button>
						)}
					</div>

					<AnimatePresence initial={false}>
						{entries.map((entry, index) => (
							<motion.div
								key={entry.id ?? entry.uid}
								initial={{ opacity: 0, height: 0, scaleY: 0.95 }}
								animate={{ opacity: 1, height: 'auto', scaleY: 1 }}
								exit={{ opacity: 0, height: 0, scaleY: 0.95 }}
								transition={{ duration: 0.25, ease: 'easeOut' }}
								style={{ originY: 0, overflow: 'hidden' }}
								className="border border-line-1 mb-4"
							>
								{/* Accordion header — always visible */}
								<button
									onClick={() => updateEntry(index, 'collapsed', !entry.collapsed)}
									className="w-full px-4 py-3 flex items-center justify-between cursor-pointer hover:bg-bg-1 transition-colors"
								>
									<div className="flex items-center gap-3">
										<motion.span
											animate={{ rotate: entry.collapsed ? 0 : 90 }}
											transition={{ duration: 0.2 }}
											className="text-fg-3 text-xs"
										>
											▶
										</motion.span>
										<span className="text-fg-3 text-[0.65rem] tracking-[0.15em]">
											ENTRY {index + 1}
										</span>
										<span className="text-accent text-sm truncate max-w-md">
    										{entry.collapsed ? (entry.title || 'Untitled') : ''}
										</span>
									</div>
									<button
										onClick={(e) => {
											e.stopPropagation();
											setDeleteIndex(index);
										}}
										className="text-danger text-[0.7rem] tracking-[0.1em] font-mono cursor-pointer hover:text-danger transition-colors"
									>
										DELETE
									</button>
								</button>

								{/* Accordion body — collapsible */}
								<AnimatePresence initial={false}>
									{!entry.collapsed && (
										<motion.div
											initial={{ height: 0, opacity: 0 }}
											animate={{ height: 'auto', opacity: 1 }}
											exit={{ height: 0, opacity: 0 }}
											transition={{ duration: 0.2, ease: 'easeOut' }}
											style={{ overflow: 'hidden' }}
										>
											<div className="px-4 pb-4 space-y-3">
												<div>
													<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
														Title
													</label>
													<input
														type="text"
														value={entry.title}
														onChange={(e) => updateEntry(index, 'title', e.target.value)}
														maxLength={100}
														className="w-full bg-bg-1 border border-line-1 text-accent text-sm font-mono px-3 py-2 outline-none focus:border-accent/50 transition-colors"
														placeholder="e.g. The Bridge"
													/>
												</div>

												<div>
													<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
														Content
													</label>
													<textarea
														value={entry.content}
														onChange={(e) => updateEntry(index, 'content', e.target.value)}
														rows={5}
														className="w-full bg-bg-1 border border-line-1 text-accent text-sm font-mono px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
														placeholder="The lore content that will be embedded and injected into prompts..."
													/>
												</div>

												<div>
													<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
														Keywords
														<span className="text-fg-3 ml-2 normal-case">comma-separated</span>
													</label>
													<input
														type="text"
														value={entry.keywords}
														onChange={(e) => updateEntry(index, 'keywords', e.target.value)}
														className="w-full bg-bg-1 border border-line-1 text-accent text-sm font-mono px-3 py-2 outline-none focus:border-accent/50 transition-colors"
														placeholder="e.g. Bridge, city, digital"
													/>
												</div>

												<div>
													<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
														Tags
														<span className="text-fg-3 ml-2 normal-case">comma-separated</span>
													</label>
													<input
														type="text"
														value={entry.tags}
														onChange={(e) => updateEntry(index, 'tags', e.target.value)}
														className="w-full bg-bg-1 border border-line-1 text-accent text-sm font-mono px-3 py-2 outline-none focus:border-accent/50 transition-colors"
														placeholder="e.g. location, worldbuilding"
													/>
												</div>
											</div>
										</motion.div>
									)}
								</AnimatePresence>
							</motion.div>
						))}

						{entries.length === 0 && (
							<div className="text-fg-3 text-sm text-center py-8">
								No entries yet.
							</div>
						)}

						<button
							onClick={addEntry}
							className="w-full border border-dashed border-line-1 text-success text-[0.75rem] tracking-[0.1em] font-mono cursor-pointer hover:border-success/50 hover:bg-green-400/5 transition-colors py-3 mt-4"
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
					className={`w-full font-mono text-[0.75rem] tracking-[0.1em] py-2 transition-colors ${
						isSaving || !name.trim() || !description.trim()
							? 'bg-[#1a1a2e] text-fg-3 cursor-default'
							: 'bg-accent/10 border border-accent text-accent hover:bg-accent/20 cursor-pointer'
					}`}
				>
					{isSaving ? 'SAVING...' : 'SAVE LOREBOOK'}
				</button>
			</div>
			{deleteIndex !== null && (
				<div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
					<div className="border border-line-1 bg-bg-0 p-6 max-w-sm w-full mx-4 space-y-4">
						<p className="text-accent text-sm">
							Delete entry "{entries[deleteIndex]?.title || `Entry ${deleteIndex + 1}`}"?
						</p>
						<p className="text-fg-3 text-xs">
							This won't take effect until you save.
						</p>
						<div className="flex gap-3 justify-end">
							<button
								onClick={() => setDeleteIndex(null)}
								className="bg-transparent border border-line-1 text-fg-3 text-[0.75rem] tracking-[0.1em] font-mono px-4 py-1.5 cursor-pointer hover:text-accent transition-colors"
							>
								CANCEL
							</button>
							<button
								onClick={() => {
									removeEntry(deleteIndex);
									setDeleteIndex(null);
								}}
								className="bg-transparent border border-danger text-danger text-[0.75rem] tracking-[0.1em] font-mono px-4 py-1.5 cursor-pointer hover:bg-danger/10 transition-colors"
							>
								CONFIRM
							</button>
						</div>
					</div>
				</div>
			)}
		</>
	);
}