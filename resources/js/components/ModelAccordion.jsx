import Accordion from './common/Accordion.jsx';

export default function ModelAccordion({ model, onUpdate, onSave, onDelete, canSave, isActive, onSelect, onDeselect }) {
	return (
		<Accordion
			label="MODEL"
			title={model.name}
			collapsed={model.collapsed}
			badge={isActive ? (
				<button
					onClick={onDeselect}
					className="text-success text-[0.6rem] tracking-[0.15em] cursor-pointer hover:text-danger transition-colors"
				>
					● ACTIVE
				</button>
			) : null}
			actions={canSave && model.id && !isActive ? (
				<button
					onClick={onSelect}
					className="text-[0.65rem] tracking-[0.1em] px-3 py-1 transition-colors button-primary cursor-pointer"
				>
					SELECT
				</button>
			) : null}
			onToggle={() => onUpdate('collapsed', !model.collapsed)}
			onDelete={onDelete}
		>
			{/* Name */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Model Name
				</label>
				<input
					type="text"
					value={model.name}
					onChange={(e) => onUpdate('name', e.target.value)}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
					placeholder="e.g. Gemma 4"
				/>
			</div>

			{/* Endpoint */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Endpoint
				</label>
				<input
					type="text"
					value={model.endpoint ?? ''}
					onChange={(e) => onUpdate('endpoint', e.target.value)}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
					placeholder="e.g. google/gemma-4-26b-a4b-it"
				/>
			</div>

			{/* Thinking toggle */}
			<div className="flex items-center gap-3">
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase">
					Thinking
				</label>
				<button
					onClick={() => onUpdate('thinking', !model.thinking)}
					className={`px-3 py-1 text-[0.7rem] tracking-[0.1em] border transition-colors cursor-pointer ${
						model.thinking
							? 'border-success text-success bg-success/10'
							: 'border-line-1 text-fg-3'
					}`}
				>
					{model.thinking ? 'ON' : 'OFF'}
				</button>
			</div>

			{/* Prompt */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Model Prompt
					<span className="text-fg-3 ml-2 normal-case">optional</span>
				</label>
				<textarea
					value={model.prompt ?? ''}
					onChange={(e) => onUpdate('prompt', e.target.value)}
					rows={3}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
					placeholder="Instructions specific to this model..."
				/>
			</div>

			{/* Config */}
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Config
					<span className="text-fg-3 ml-2 normal-case">JSON, optional</span>
				</label>
				<textarea
					value={
						typeof model.config === 'object' && model.config
							? JSON.stringify(model.config, null, 2)
							: model.config ?? ''
					}
					onChange={(e) => onUpdate('config', e.target.value)}
					rows={4}
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none font-mono text-xs"
					placeholder='{"max_tokens": 4096, "thinking_budget": 10000}'
				/>
			</div>

			{/* Save */}
			<button
				onClick={onSave}
				disabled={model.saving || !model.name.trim() || !canSave}
				className={`w-full text-[0.75rem] tracking-[0.1em] py-2 transition-colors ${
					model.saving || !model.name.trim() || !canSave
						? 'bg-bg-3 text-fg-3 cursor-default'
						: 'button-success cursor-pointer'
				}`}
			>
				{!canSave
					? 'SAVE PROVIDER FIRST'
					: model.saving
						? 'SAVING...'
						: 'SAVE MODEL'}
			</button>

		</Accordion>
	);
}