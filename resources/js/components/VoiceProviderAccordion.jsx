import { useState } from 'react';
import { AnimatePresence } from 'framer-motion';
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
	addToast,
}) {
	const [collapsedModels, setCollapsedModels] = useState({});

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
		>
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
					{provider.models.map((model) => (
						<VoiceModelAccordion
							key={model.id}
							model={model}
							collapsed={collapsedModels[model.id] ?? true}
							onToggle={() =>
								setCollapsedModels((prev) => ({ ...prev, [model.id]: !(prev[model.id] ?? true) }))
							}
							isActive={model.id === activeModelId}
							activeVoice={activeVoice}
							onChooseVoice={(voice) => onChooseVoice(model.id, voice)}
							onDeactivate={onDeactivateModel}
							addToast={addToast}
						/>
					))}
				</AnimatePresence>
			</div>
		</Accordion>
	);
}
