import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { route } from 'ziggy-js';
import Accordion from './common/Accordion.jsx';
import VoiceModelAccordion from './VoiceModelAccordion.jsx';
import PromptTreeEditor from './PromptTreeEditor.jsx';
import usePromptTree from '../hooks/usePromptTree.js';
import { api } from '../utils/api.js';

const URL_PATTERN = /(https?:\/\/[^\s]+)/g;

function linkify(text) {
	// split() on a capturing group interleaves matches at odd indices — no stateful regex.test() needed
	return text.split(URL_PATTERN).map((part, i) =>
		i % 2 === 1 ? (
			<a
				key={i}
				href={part}
				target="_blank"
				rel="noopener noreferrer"
				className="text-accent underline hover:text-accent/80"
			>
				{part}
			</a>
		) : (
			part
		)
	);
}

export default function VoiceProviderAccordion({
	provider,
	collapsed,
	onToggle,
	activeModelId,
	activeVoice,
	onChooseVoice,
	onDeactivateModel,
	onUpdate,
	onSave,
	onDelete,
	onAddModel,
	onUpdateModel,
	onSaveModel,
	onDeleteModel,
	addToast,
}) {
	const [collapsedModels, setCollapsedModels] = useState({});
	const [configCollapsed, setConfigCollapsed] = useState(true);

	const promptTree = usePromptTree(
		provider.prompt,
		async (sections) => {
			const res = await api.patch(route('voice-providers.updatePrompt', { id: provider.id }), { prompt: sections });
			if (!res.ok) throw new Error('Failed to save prompt');
		},
		addToast
	);

	return (
		<Accordion
			label="PROVIDER"
			title={provider.name}
			collapsed={collapsed}
			onToggle={onToggle}
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
								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Name
									</label>
									<input
										type="text"
										value={provider.name}
										onChange={(e) => onUpdate('name', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder="e.g. Deepgram"
									/>
								</div>

								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										URL
									</label>
									<input
										type="text"
										value={provider.url}
										onChange={(e) => onUpdate('url', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
										placeholder="e.g. https://api.deepgram.com"
									/>
								</div>

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

								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Format
									</label>
									<select
										value={provider.format}
										onChange={(e) => onUpdate('format', e.target.value)}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
									>
										<option value="openai_compatible">OpenAI-compatible (self-hosted)</option>
										<option value="openai_tts">OpenAI TTS</option>
										<option value="deepgram">Deepgram</option>
										<option value="elevenlabs">ElevenLabs</option>
									</select>
								</div>

								<div>
									<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
										Instructions
										<span className="text-fg-3 ml-2 normal-case">optional, shown above this provider's models</span>
									</label>
									<textarea
										value={provider.instructions ?? ''}
										onChange={(e) => onUpdate('instructions', e.target.value)}
										rows={3}
										className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none"
										placeholder="Setup notes shown to whoever picks this provider..."
									/>
								</div>

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

			{provider.instructions && (
				<pre className="text-fg-2 text-[0.7rem] leading-relaxed border-l-2 border-line-1 pl-3 whitespace-pre-wrap font-mono">
					{linkify(provider.instructions)}
				</pre>
			)}

			<div>
				<span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
					Provider Prompt
					<span className="text-fg-3 ml-2 normal-case">injected into voice-mode conversations while this provider is active</span>
				</span>
				<div className="mt-2">
					<PromptTreeEditor promptTree={promptTree} />
				</div>
			</div>

			<div>
                <span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
                    Models ({provider.models.length})
                </span>

				<AnimatePresence initial={false}>
					{provider.models.map((model, mi) => {
						const key = model.id ?? model.uid;
						return (
							<VoiceModelAccordion
								key={key}
								model={model}
								collapsed={collapsedModels[key] ?? true}
								onToggle={() =>
									setCollapsedModels((prev) => ({ ...prev, [key]: !(prev[key] ?? true) }))
								}
								isActive={model.id === activeModelId}
								activeVoice={activeVoice}
								onChooseVoice={(voice) => onChooseVoice(model.id, voice)}
								onDeactivate={onDeactivateModel}
								canSave={!!provider.id}
								onUpdate={(field, value) => onUpdateModel(mi, field, value)}
								onSave={() => onSaveModel(mi)}
								onDelete={() => onDeleteModel(mi)}
								addToast={addToast}
							/>
						);
					})}
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
