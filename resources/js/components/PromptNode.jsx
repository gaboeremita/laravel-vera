import { useState } from 'react';
import { AnimatePresence } from 'framer-motion';
import Accordion from './common/Accordion.jsx';

export default function PromptNode({
									   path,
									   label,
									   value,
									   depth = 0,
									   onUpdateValue,
									   onAddKey,
									   onRemoveKey,
									   onRenameKey,
									   onAddListItem,
									   onRemoveListItem,
									   onUpdateListItem,
									   onRequestDelete,
								   }) {
	const [collapsed, setCollapsed] = useState(true);
	const [isAdding, setIsAdding] = useState(false);
	const [newKeyName, setNewKeyName] = useState('');
	const [newKeyType, setNewKeyType] = useState('string');

	const keyName = path[path.length - 1];
	const depthLabel = depth === 0 ? 'SECTION' : 'KEY';

	const handleAddKey = () => {
		const trimmed = newKeyName.trim();
		if (!trimmed) return;

		onAddKey(path, trimmed, newKeyType);
		setNewKeyName('');
		setNewKeyType('string');
		setIsAdding(false);
	};

	const handleCancelAdd = () => {
		setNewKeyName('');
		setNewKeyType('string');
		setIsAdding(false);
	};

	// String value
	if (typeof value === 'string') {
		return (
			<Accordion
				label={depthLabel}
				title={label}
				collapsed={collapsed}
				onToggle={() => setCollapsed(!collapsed)}
				onDelete={onRequestDelete}
			>
				{/* Key name */}
				<div>
					<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
						Key
					</label>
					<input
						type="text"
						value={keyName}
						onChange={(e) => onRenameKey(path, e.target.value)}
						className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
					/>
				</div>

				{/* Value */}
				<div>
					<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
						Value
					</label>
					<textarea
						value={value}
						onChange={(e) => onUpdateValue(path, e.target.value)}
						rows={3}
						className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
					/>
				</div>
			</Accordion>
		);
	}

	// Sequential array (list of strings)
	if (Array.isArray(value)) {
		return (
			<Accordion
				label={depthLabel}
				title={`${label} (${value.length})`}
				collapsed={collapsed}
				onToggle={() => setCollapsed(!collapsed)}
				onDelete={onRequestDelete}
			>
				{/* Key name */}
				<div>
					<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
						Key
					</label>
					<input
						type="text"
						value={keyName}
						onChange={(e) => onRenameKey(path, e.target.value)}
						className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
					/>
				</div>

				{/* List items */}
				<div className="space-y-2">
					<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block">
						Items ({value.length})
					</label>

					{value.map((item, i) => (
						<div key={i} className="flex gap-2">
                            <textarea
								value={item}
								onChange={(e) => onUpdateListItem(path, i, e.target.value)}
								rows={2}
								className="flex-1 bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
							/>
							<button
								onClick={() => onRemoveListItem(path, i)}
								className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger transition-colors self-start pt-2"
							>
								✕
							</button>
						</div>
					))}

					<button
						onClick={() => onAddListItem(path)}
						className="w-full text-[0.7rem] tracking-[0.1em] py-2 border border-dashed border-line-1 text-success cursor-pointer hover:border-success/50 hover:bg-bg-1 transition-colors"
					>
						+ ADD ITEM
					</button>
				</div>
			</Accordion>
		);
	}

	// Associative object — recurse
	const entries = Object.entries(value);

	return (
		<Accordion
			label={depthLabel}
			title={`${label} (${entries.length})`}
			collapsed={collapsed}
			onToggle={() => setCollapsed(!collapsed)}
			onDelete={onRequestDelete}
		>
			{/* Key name */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Key
				</label>
				<input
					type="text"
					value={keyName}
					onChange={(e) => onRenameKey(path, e.target.value)}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
				/>
			</div>

			{/* Child nodes */}
			<div className="space-y-2">
				<AnimatePresence initial={false}>
					{entries.map(([childKey, childValue]) => (
						<PromptNode
							key={childKey}
							path={[...path, childKey]}
							label={childKey}
							value={childValue}
							depth={depth + 1}
							onUpdateValue={onUpdateValue}
							onAddKey={onAddKey}
							onRemoveKey={onRemoveKey}
							onRenameKey={onRenameKey}
							onAddListItem={onAddListItem}
							onRemoveListItem={onRemoveListItem}
							onUpdateListItem={onUpdateListItem}
							onRequestDelete={() => onRemoveKey([...path, childKey])}
						/>
					))}
				</AnimatePresence>
			</div>

			{/* Add sub-key */}
			{!isAdding ? (
				<button
					onClick={() => setIsAdding(true)}
					className="w-full text-[0.7rem] tracking-[0.1em] py-2 border border-dashed border-line-1 text-success cursor-pointer hover:border-success/50 hover:bg-bg-1 transition-colors"
				>
					+ ADD KEY
				</button>
			) : (
				<div className="border border-line-1 p-3 space-y-3">
					{/* Key name input */}
					<div>
						<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
							New Key Name
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

					{/* Type selector */}
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

					{/* Confirm / Cancel */}
					<div className="flex justify-end gap-2">
						<button
							onClick={handleCancelAdd}
							className="px-4 py-1.5 text-[0.7rem] tracking-[0.1em] border border-line-1 text-fg-3 cursor-pointer hover:text-fg-1 transition-colors"
						>
							CANCEL
						</button>
						<button
							onClick={handleAddKey}
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
		</Accordion>
	);
}