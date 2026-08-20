import { route } from 'ziggy-js';
import Accordion from './common/Accordion.jsx';
import PromptTreeEditor from './PromptTreeEditor.jsx';
import usePromptTree from '../hooks/usePromptTree.js';
import { api } from '../utils/api.js';

export default function DiscordDmAccordion({ dm, collapsed, onToggle, assistantId, addToast }) {
	const promptTree = usePromptTree(
		dm.prompt,
		async (sections) => {
			const res = await api.put(
				route('discord.channels.updatePrompt', { assistant: assistantId, channelId: dm.id }),
				{ prompt: sections }
			);
			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Failed to save prompt');
			}
		},
		addToast
	);

	return (
		<Accordion
			label="DM"
			title={dm.name}
			collapsed={collapsed}
			onToggle={onToggle}
		>
			<div>
				<span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
					DM Prompt
					<span className="text-fg-3 ml-2 normal-case">injected only when replying in this DM</span>
				</span>
				<div className="mt-2">
					<PromptTreeEditor promptTree={promptTree} />
				</div>
			</div>
		</Accordion>
	);
}
