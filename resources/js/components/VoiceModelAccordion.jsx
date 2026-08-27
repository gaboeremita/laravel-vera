import { useState, useEffect } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { route } from 'ziggy-js';
import Accordion from './common/Accordion.jsx';
import PromptTreeEditor from './PromptTreeEditor.jsx';
import usePromptTree from '../hooks/usePromptTree.js';
import { api } from '../utils/api.js';

export default function VoiceModelAccordion({
	model,
	collapsed,
	onToggle,
	isActive,
	activeVoice,
	onChooseVoice,
	onDeactivate,
	canSave,
	onUpdate,
	onSave,
	onDelete,
	addToast,
}) {
	const [voiceInput, setVoiceInput] = useState(isActive ? (activeVoice || '') : '');
	const [configCollapsed, setConfigCollapsed] = useState(true);
	const datalistId = `voices-${model.id ?? model.uid}`;

	const promptTree = usePromptTree(
		model.prompt,
		async (sections) => {
			const res = await api.patch(
				route('voice-models.updatePrompt', { provider: model.provider_id, model: model.id }),
				{ prompt: sections }
			);
			if (!res.ok) throw new Error('Failed to save prompt');
		},
		addToast
	);

	// Reset the local input whenever the active voice changes externally,
	// computed here instead of in an effect so it applies before the next
	// paint rather than one render later.
	const activeVoiceKey = `${isActive}|${activeVoice}`;
	const [lastActiveVoiceKey, setLastActiveVoiceKey] = useState(activeVoiceKey);
	if (activeVoiceKey !== lastActiveVoiceKey) {
		setLastActiveVoiceKey(activeVoiceKey);
		setVoiceInput(isActive ? (activeVoice || '') : '');
	}

	const commit = () => {
		const value = voiceInput.trim();
		if (value && value !== activeVoice) {
			onChooseVoice(value);
		}
	};

	return (
		<Accordion
			label="MODEL"
			title={model.name}
			collapsed={collapsed}
			onToggle={onToggle}
			onDelete={onDelete}
			badge={isActive ? (
				<button
					onClick={onDeactivate}
					className="text-success text-[0.6rem] tracking-[0.15em] cursor-pointer hover:text-danger transition-colors"
				>
					● ACTIVE
				</button>
			) : null}
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
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Model Name
									</label>
									<input
										type="text"
										value={model.name}
										onChange={(e) => onUpdate('name', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder="e.g. Aura 2"
									/>
								</div>

								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Endpoint
										<span className="text-fg-3 ml-2 normal-case">path appended to the provider's URL</span>
									</label>
									<input
										type="text"
										value={model.endpoint ?? ''}
										onChange={(e) => onUpdate('endpoint', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder="e.g. v1/speak"
									/>
								</div>

								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Voices
										<span className="text-fg-3 ml-2 normal-case">one per line</span>
									</label>
									<textarea
										value={Array.isArray(model.voices) ? model.voices.join('\n') : ''}
										onChange={(e) => onUpdate('voices', e.target.value.split('\n').map((v) => v.trim()).filter(Boolean))}
										rows={4}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none font-mono text-xs"
										placeholder={'aura-2-thalia-en\naura-2-luna-en'}
									/>
								</div>

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
										placeholder="{}"
									/>
								</div>

								<button
									onClick={onSave}
									disabled={model.saving || !model.name.trim() || !canSave}
									className={`w-full text-[0.75rem] tracking-[0.1em] py-2 transition-colors ${
										model.saving || !model.name.trim() || !canSave
											? 'bg-bg-3 text-fg-3 cursor-default'
											: 'button-success cursor-pointer'
									}`}
								>
									{!canSave ? 'SAVE PROVIDER FIRST' : model.saving ? 'SAVING...' : 'SAVE MODEL'}
								</button>
							</div>
						</motion.div>
					)}
				</AnimatePresence>
			</div>

			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Voice
					{!isActive && <span className="text-fg-3 ml-2 normal-case">picking a voice activates this model</span>}
				</label>
				<input
					type="text"
					list={datalistId}
					value={voiceInput}
					onChange={(e) => setVoiceInput(e.target.value)}
					onBlur={commit}
					onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()}
					placeholder="Exact voice name — check the provider's own UI for what's actually loaded"
					className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
				/>
				<datalist id={datalistId}>
					{model.voices.map((v) => (
						<option key={v} value={v} />
					))}
				</datalist>
				{model.voices.length > 0 && (
					<div className="text-fg-3 text-[0.65rem] mt-1">
						Seeded as of setup: {model.voices.slice(0, 6).join(', ')}
						{model.voices.length > 6 && ` +${model.voices.length - 6} more (start typing to autocomplete)`} — may be stale if the model was swapped externally.
					</div>
				)}
			</div>

			<div>
				<span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
					Model Prompt
					<span className="text-fg-3 ml-2 normal-case">injected into voice-mode conversations while this model is active</span>
				</span>
				<div className="mt-2">
					<PromptTreeEditor promptTree={promptTree} />
				</div>
			</div>
		</Accordion>
	);
}
