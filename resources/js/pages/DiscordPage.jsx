import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import Header from '../components/Header.jsx';
import DiscordServerAccordion from '../components/DiscordServerAccordion.jsx';
import DiscordDmAccordion from '../components/DiscordDmAccordion.jsx';
import useDiscordSettings from '../hooks/useDiscordSettings.js';
import { getAssistantMenuItems } from '../utils/assistantMenu.jsx';

export default function DiscordPage() {
	const navigate = useNavigate();
	const { addToast, assistantId } = useOutletContext();
	const [collapsedServers, setCollapsedServers] = useState({});
	const [collapsedDms, setCollapsedDms] = useState({});

	const { guilds, dms, isLoading, discoveryError, setChannelTrigger } = useDiscordSettings(addToast, assistantId);

	if (isLoading) {
		return (
			<>
				<Header settingsPath={`/assistants/${assistantId}/settings`}
				menuItems={getAssistantMenuItems(assistantId)}
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					onBack={() => navigate(-1)}
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
				menuItems={getAssistantMenuItems(assistantId)}
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`SERVERS: ${guilds.length}`}
				onBack={() => navigate(-1)}
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

				{dms.length > 0 && (
					<>
						<span className="text-fg-3 text-[0.65rem] tracking-[0.15em] uppercase block pt-2">
							Direct Messages ({dms.length})
						</span>
						<AnimatePresence initial={false}>
							{dms.map((dm) => (
								<DiscordDmAccordion
									key={dm.id}
									dm={dm}
									collapsed={collapsedDms[dm.id] ?? true}
									onToggle={() =>
										setCollapsedDms((prev) => ({ ...prev, [dm.id]: !(prev[dm.id] ?? true) }))
									}
									assistantId={assistantId}
									addToast={addToast}
								/>
							))}
						</AnimatePresence>
					</>
				)}
			</div>
		</>
	);
}
