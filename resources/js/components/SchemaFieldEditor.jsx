import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import SchemaEditor from './SchemaEditor.jsx';

const TYPES = ['string', 'integer', 'float', 'boolean', 'enum', 'object'];
const SECTIONS = ['primary', 'advanced'];

export default function SchemaFieldEditor({ field, onChange, onRemove, showSection }) {
	const [collapsed, setCollapsed] = useState(true);
	const update = (key, value) => onChange({ ...field, [key]: value });

	const handleOptionKeyDown = (e) => {
		if (e.key === 'Enter' && e.target.value.trim()) {
			const val = e.target.value.trim();
			const current = field.options ?? [];
			if (!current.includes(val)) {
				update('options', [...current, val]);
			}
			e.target.value = '';
			e.preventDefault();
		}
	};

	const removeOption = (opt) => {
		update('options', (field.options ?? []).filter((o) => o !== opt));
	};

	return (
		<div className="border border-line-1">
			{/* Header */}
			<div
				onClick={() => setCollapsed(!collapsed)}
				className="w-full px-4 py-3 flex items-center justify-between cursor-pointer hover:bg-bg-1 transition-colors"
			>
				<div className="flex items-center gap-3">
					<motion.span
						animate={{ rotate: collapsed ? 0 : 90 }}
						transition={{ duration: 0.2 }}
						className="text-fg-3 text-xs"
					>
						▶
					</motion.span>
					<span className="text-accent text-sm truncate max-w-xs">
						{field.name || 'Untitled'}
					</span>
					<span className="text-fg-3 text-[0.6rem] tracking-[0.15em] uppercase">
						{field.type ?? 'string'}
					</span>
					{field.required && (
						<span className="text-warning text-[0.6rem] tracking-[0.1em] uppercase">required</span>
					)}
				</div>
				<div onClick={(e) => e.stopPropagation()}>
					<button
						onClick={onRemove}
						className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger/70 transition-colors"
					>
						DELETE
					</button>
				</div>
			</div>

			{/* Body */}
			<AnimatePresence initial={false}>
				{!collapsed && (
					<motion.div
						initial={{ height: 0, opacity: 0 }}
						animate={{ height: 'auto', opacity: 1 }}
						exit={{ height: 0, opacity: 0 }}
						transition={{ duration: 0.2, ease: 'easeOut' }}
						style={{ overflow: 'hidden' }}
					>
						<div className="pl-8 pr-4 pb-4 space-y-3">
							{/* Name */}
							<div>
								<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">Name</label>
								<input
									type="text"
									value={field.name ?? ''}
									onChange={(e) => update('name', e.target.value)}
									className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
									placeholder="e.g. max_tokens"
								/>
							</div>

							{/* Type + Section */}
							<div className="flex gap-3">
								<div className="flex-1">
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">Type</label>
									<select
										value={field.type ?? 'string'}
										onChange={(e) => update('type', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
									>
										{TYPES.map((t) => (
											<option key={t} value={t}>{t}</option>
										))}
									</select>
								</div>
								{showSection && (
									<div className="flex-1">
										<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">Section</label>
										<select
											value={field.section ?? 'primary'}
											onChange={(e) => update('section', e.target.value)}
											className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										>
											{SECTIONS.map((s) => (
												<option key={s} value={s}>{s}</option>
											))}
										</select>
									</div>
								)}
							</div>

							{/* Default + Required */}
							<div className="flex gap-3">
								<div className="flex-1">
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Default
										<span className="text-fg-3 ml-2 normal-case">optional</span>
									</label>
									<input
										type="text"
										value={field.default ?? ''}
										onChange={(e) => update('default', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder="leave blank for none"
									/>
								</div>
								<div className="flex-1">
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">Required?</label>
									<div className="flex items-center gap-3 h-[38px]">
										<button
											onClick={() => update('required', !(field.required ?? false))}
											className="relative w-12 h-6 rounded-full cursor-pointer transition-colors duration-200 border border-line-1"
											style={{ backgroundColor: field.required ? 'var(--color-accent)' : 'var(--color-bg-1)' }}
											aria-pressed={field.required ?? false}
										>
											<span
												className="absolute top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-white shadow transition-all duration-200"
												style={{ left: field.required ? 'calc(100% - 20px)' : '4px' }}
											/>
										</button>
										<span className={`text-[0.65rem] tracking-[0.1em] uppercase ${field.required ? 'text-accent' : 'text-fg-3'}`}>
											{field.required ? 'Yes' : 'No'}
										</span>
									</div>
								</div>
							</div>

							{/* Min/Max — integer and float only */}
							{(field.type === 'integer' || field.type === 'float') && (
								<div className="flex gap-3">
									<div className="flex-1">
										<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
											Min
											<span className="text-fg-3 ml-2 normal-case">optional</span>
										</label>
										<input
											type="number"
											value={field.min ?? ''}
											onChange={(e) => update('min', e.target.value === '' ? undefined : Number(e.target.value))}
											className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
											placeholder="no minimum"
										/>
									</div>
									<div className="flex-1">
										<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
											Max
											<span className="text-fg-3 ml-2 normal-case">optional</span>
										</label>
										<input
											type="number"
											value={field.max ?? ''}
											onChange={(e) => update('max', e.target.value === '' ? undefined : Number(e.target.value))}
											className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
											placeholder="no maximum"
										/>
									</div>
								</div>
							)}

							{/* Options — enum only */}
							{field.type === 'enum' && (
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">Options</label>
									<div className="flex flex-wrap gap-1 mb-2 min-h-[1.5rem]">
										{(field.options ?? []).map((opt) => (
											<span
												key={opt}
												className="flex items-center gap-1 bg-accent/10 border border-accent/30 text-accent text-[0.65rem] tracking-[0.05em] px-2 py-1"
											>
												{opt}
												<button
													onClick={() => removeOption(opt)}
													className="text-danger cursor-pointer hover:text-danger/70 transition-colors leading-none ml-1"
												>
													✕
												</button>
											</span>
										))}
									</div>
									<input
										type="text"
										onKeyDown={handleOptionKeyDown}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder="Type a value and press Enter to add"
									/>
								</div>
							)}

							{/* Children — object only */}
							{field.type === 'object' && (
								<div className="border-l-2 border-line-1 pl-3">
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-2">Children</label>
									<SchemaEditor
										schema={field.children ?? []}
										onChange={(children) => update('children', children)}
										nested
									/>
								</div>
							)}
						</div>
					</motion.div>
				)}
			</AnimatePresence>
		</div>
	);
}
