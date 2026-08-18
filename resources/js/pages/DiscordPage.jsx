import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import Header from '../components/Header.jsx';
import DiscordServerAccordion from '../components/DiscordServerAccordion.jsx';
import useDiscordSettings from '../hooks/useDiscordSettings.js';

export default function DiscordPage() {
	const navigate = useNavigate();
	const { addToast, assistantId } = useOutletContext();
	const [collapsedServers, setCollapsedServers] = useState({});

	const { guilds, isLoading, discoveryError, setChannelTrigger } = useDiscordSettings(addToast, assistantId);

	if (isLoading) {
		return (
			<>
				<Header settingsPath={`/assistants/${assistantId}/settings`}
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					actions={
						<button onClick={() => navigate(-1)} className="button-primary">
							← PREVIOUS PAGE
						</button>
					}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Discord</span>
				</Header>
				<div className="flex-1 p-5">
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				</div>
			</>
		);
	}

	return (
		<>
			<Header settingsPath={`/assistants/${assistantId}/settings`}
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`SERVERS: ${guilds.length}`}
				actions={
					<button onClick={() => navigate(-1)} className="button-primary">
						← PREVIOUS PAGE
					</button>
				}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Discord</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar space-y-4">
				<AnimatePresence initial={false}>
					{guilds.map((guild) => (
						<DiscordServerAccordion
							key={guild.id}
							guild={guild}
							collapsed={collapsedServers[guild.id] ?? true}
							onToggle={() =>
								setCollapsedServers((prev) => ({ ...prev, [guild.id]: !(prev[guild.id] ?? true) }))
							}
							onTriggerModeChange={setChannelTrigger}
							assistantId={assistantId}
							addToast={addToast}
						/>
					))}
				</AnimatePresence>

				{guilds.length === 0 && (
					<div className="text-fg-3 text-sm text-center py-8">
						{discoveryError ?? 'No Discord servers found for this assistant.'}
					</div>
				)}
			</div>
		</>
	);
}
