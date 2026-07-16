import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import Accordion from './common/Accordion.jsx';
import ModelAccordion from './ModelAccordion.jsx';
import SchemaEditor from './SchemaEditor.jsx';

export default function ProviderAccordion({
											  provider,
											  onUpdate,
											  onSave,
											  onDelete,
											  onAddModel,
											  onUpdateModel,
											  onSaveModel,
											  onDeleteModel,
											  activeModelId,
											  onSelectModel,
										  }) {
	const [configCollapsed, setConfigCollapsed] = useState(true);

	return (
		<Accordion
			label="PROVIDER"
			title={provider.name}
			collapsed={provider.collapsed}
			onToggle={() => onUpdate('collapsed', !provider.collapsed)}
			onDelete={onDelete}
		>
			{/* Configuration section — collapsible */}
			<div>
				<button
					onClick={() => setConfigCollapsed(!configCollapsed)}
					className="flex items-center gap-2 cursor-pointer hover:text-accent transition-colors"
				>
					<motion.span
						animate={{ rotate: configCollapsed ? 0 : 90 }}
						transition={{ duration: 0.2 }}
						className="text-fg-3 text-xs"
					>
						▶
					</motion.span>
					<span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
                        Configuration
                    </span>
				</button>

				<AnimatePresence initial={false}>
					{!configCollapsed && (
						<motion.div
							initial={{ height: 0, opacity: 0 }}
							animate={{ height: 'auto', opacity: 1 }}
							exit={{ height: 0, opacity: 0 }}
							transition={{ duration: 0.2, ease: 'easeOut' }}
							style={{ overflow: 'hidden' }}
						>
							<div className="space-y-3 mt-3">
								{/* Name */}
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Name
									</label>
									<input
										type="text"
										value={provider.name}
										onChange={(e) => onUpdate('name', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder="e.g. OpenRouter"
									/>
								</div>

								{/* URL */}
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										URL
									</label>
									<input
										type="text"
										value={provider.url}
										onChange={(e) => onUpdate('url', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder="e.g. https://openrouter.ai/api/v1/chat/completions"
									/>
								</div>

								{/* API Key */}
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										API Key
										{provider.hasKey && (
											<span className="text-success ml-2 normal-case">● set</span>
										)}
									</label>
									<input
										type="password"
										value={provider.api_key}
										onChange={(e) => onUpdate('api_key', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder={provider.hasKey ? '••••••••  (leave blank to keep current)' : 'Enter API key'}
									/>
								</div>

								{/* Format */}
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Format
									</label>
									<select
										value={provider.format}
										onChange={(e) => onUpdate('format', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
									>
										<option value="generic">Generic (OpenAI-compatible)</option>
										<option value="anthropic">Anthropic</option>
									</select>
								</div>

								{/* Prompt */}
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Provider Prompt
										<span className="text-fg-3 ml-2 normal-case">optional</span>
									</label>
									<textarea
										value={provider.prompt ?? ''}
										onChange={(e) => onUpdate('prompt', e.target.value)}
										rows={3}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
										placeholder="Instructions injected for all models under this provider..."
									/>
								</div>

								{/* Config Schema */}
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Config Schema
									</label>
									<SchemaEditor
										schema={Array.isArray(provider.config_schema) ? provider.config_schema : []}
										onChange={(schema) => onUpdate('config_schema', schema)}
									/>
								</div>

								{/* Save — right-aligned, not full width */}
								<div className="flex justify-end">
									<button
										onClick={onSave}
										disabled={provider.saving || !provider.name.trim() || !provider.url.trim()}
										className={`text-[0.75rem] tracking-[0.1em] px-6 py-2 transition-colors ${
											provider.saving || !provider.name.trim() || !provider.url.trim()
												? 'bg-bg-3 text-fg-3 cursor-default'
												: 'button-success cursor-pointer'
										}`}
									>
										{provider.saving ? 'SAVING...' : 'SAVE PROVIDER CONFIGURATION'}
									</button>
								</div>
							</div>
						</motion.div>
					)}
				</AnimatePresence>
			</div>

			{/* Divider */}
			<div className="text-line-1 text-[0.7rem]">
				─────────────────────────────────
			</div>

			{/* Models */}
			<div>
                <span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
                    Models ({provider.models.length})
                </span>

				<AnimatePresence initial={false}>
					{provider.models.map((model, mi) => (
						<ModelAccordion
							key={model.id ?? model.uid}
							model={model}
							configSchema={Array.isArray(provider.config_schema) ? provider.config_schema : []}
							canSave={!!provider.id}
							isActive={model.id === activeModelId}
							onUpdate={(field, value) => onUpdateModel(mi, field, value)}
							onSave={() => onSaveModel(mi)}
							onDelete={() => onDeleteModel(mi)}
							onSelect={() => onSelectModel(model.id)}
							onDeselect={() => onSelectModel(null)}
						/>
					))}
				</AnimatePresence>

				<button
					onClick={onAddModel}
					disabled={!provider.id}
					className={`w-full text-[0.75rem] tracking-[0.1em] py-3 mt-3 transition-colors border border-dashed ${
						provider.id
							? 'border-line-1 text-success cursor-pointer hover:border-success/50 hover:bg-bg-1'
							: 'border-line-1 text-fg-3 cursor-default'
					}`}
				>
					+ ADD MODEL
				</button>
			</div>
		</Accordion>
	);
}