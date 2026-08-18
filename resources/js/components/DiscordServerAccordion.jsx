import { useState } from 'react';
import { AnimatePresence } from 'framer-motion';
import { route } from 'ziggy-js';
import Accordion from './common/Accordion.jsx';
import DiscordChannelAccordion from './DiscordChannelAccordion.jsx';
import PromptTreeEditor from './PromptTreeEditor.jsx';
import usePromptTree from '../hooks/usePromptTree.js';
import { api } from '../utils/api.js';

export default function DiscordServerAccordion({ guild, collapsed, onToggle, onTriggerModeChange, assistantId, addToast }) {
	const [collapsedChannels, setCollapsedChannels] = useState({});

	const promptTree = usePromptTree(
		guild.prompt,
		async (sections) => {
			const res = await api.put(
				route('discord.servers.updatePrompt', { assistant: assistantId, guildId: guild.id }),
				{ prompt: sections }
			);
			if (!res.ok) throw new Error('Failed to save prompt');
		},
		addToast
	);

	return (
		<Accordion
			label="SERVER"
			title={guild.name}
			collapsed={collapsed}
			onToggle={onToggle}
		>
			<div>
				<span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
					Server Prompt
					<span className="text-fg-3 ml-2 normal-case">injected whenever replying anywhere in this server</span>
				</span>
				<div className="mt-2">
					<PromptTreeEditor promptTree={promptTree} />
				</div>
			</div>

			<div>
				<span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
					Channels ({guild.channels.length})
				</span>

				<AnimatePresence initial={false}>
					{guild.channels.map((channel) => (
						<DiscordChannelAccordion
							key={channel.id}
							channel={channel}
							guild={guild}
							triggerMode={channel.trigger_mode ?? 'off'}
							collapsed={collapsedChannels[channel.id] ?? true}
							onToggle={() =>
								setCollapsedChannels((prev) => ({ ...prev, [channel.id]: !(prev[channel.id] ?? true) }))
							}
							onTriggerModeChange={onTriggerModeChange}
							assistantId={assistantId}
							addToast={addToast}
						/>
					))}
				</AnimatePresence>
			</div>
		</Accordion>
	);
}
