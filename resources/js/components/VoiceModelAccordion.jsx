import { useState, useEffect } from 'react';
import { route } from 'ziggy-js';
import Accordion from './common/Accordion.jsx';
import PromptTreeEditor from './PromptTreeEditor.jsx';
import usePromptTree from '../hooks/usePromptTree.js';
import { api } from '../utils/api.js';

export default function VoiceModelAccordion({ model, collapsed, onToggle, isActive, activeVoice, onChooseVoice, onDeactivate, addToast }) {
	const [voiceInput, setVoiceInput] = useState(isActive ? (activeVoice || '') : '');
	const datalistId = `voices-${model.id}`;

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

	useEffect(() => {
		setVoiceInput(isActive ? (activeVoice || '') : '');
	}, [isActive, activeVoice]);

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
			badge={isActive ? (
				<button
					onClick={onDeactivate}
					className="text-success text-[0.6rem] tracking-[0.15em] cursor-pointer hover:text-danger transition-colors"
				>
					● ACTIVE
				</button>
			) : null}
		>
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
						Seeded as of setup: {model.voices.join(', ')} — may be stale if the model was swapped externally.
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
