import { route } from 'ziggy-js';
import Accordion from './common/Accordion.jsx';
import PromptTreeEditor from './PromptTreeEditor.jsx';
import usePromptTree from '../hooks/usePromptTree.js';
import { api } from '../utils/api.js';

const TRIGGER_MODES = [
	{ value: 'off', label: 'OFF' },
	{ value: 'always', label: 'ALWAYS' },
	{ value: 'mention', label: 'ON MENTION' },
];

export default function DiscordChannelAccordion({
	channel,
	guild,
	triggerMode,
	collapsed,
	onToggle,
	onTriggerModeChange,
	assistantId,
	addToast,
}) {
	const promptTree = usePromptTree(
		channel.prompt,
		async (sections) => {
			const res = await api.put(
				route('discord.channels.updatePrompt', { assistant: assistantId, channelId: channel.id }),
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
			label="CHANNEL"
			title={`#${channel.name}`}
			collapsed={collapsed}
			onToggle={onToggle}
			badge={triggerMode !== 'off' ? (
				<span className="text-success text-[0.6rem] tracking-[0.15em]">● {triggerMode.toUpperCase()}</span>
			) : null}
		>
			<div>
				<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
					Respond
				</label>
				<select
					value={triggerMode}
					onChange={(e) => onTriggerModeChange(guild, channel, e.target.value)}
					className="bg-bg-1 border border-line-1 text-fg-1 text-[0.75rem] tracking-[0.05em] px-3 py-1.5 cursor-pointer outline-none focus:border-accent transition-colors"
				>
					{TRIGGER_MODES.map((m) => (
						<option key={m.value} value={m.value}>
							{m.label}
						</option>
					))}
				</select>
			</div>

			<div>
				<span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase">
					Channel Prompt
					<span className="text-fg-3 ml-2 normal-case">injected only when replying in this channel — set a trigger mode above first</span>
				</span>
				<div className="mt-2">
					<PromptTreeEditor promptTree={promptTree} />
				</div>
			</div>
		</Accordion>
	);
}
