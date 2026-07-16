import Accordion from './common/Accordion.jsx';
import SchemaForm from './SchemaForm.jsx';

export default function ModelAccordion({ model, configSchema, onUpdate, onSave, onDelete, canSave, isActive, onSelect, onDeselect }) {
	const hasSchema = Array.isArray(configSchema) && configSchema.length > 0;

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
					{!hasSchema && <span className="text-fg-3 ml-2 normal-case">JSON, optional</span>}
				</label>
				{hasSchema ? (
					<SchemaForm
						schema={configSchema}
						config={typeof model.config === 'object' && model.config ? model.config : {}}
						onChange={(v) => onUpdate('config', v)}
					/>
				) : (
					<textarea
						value={
							typeof model.config === 'object' && model.config
								? JSON.stringify(model.config, null, 2)
								: model.config ?? ''
						}
						onChange={(e) => onUpdate('config', e.target.value)}
						rows={4}
						className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none font-mono text-xs"
						placeholder="{}"
					/>
				)}
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
