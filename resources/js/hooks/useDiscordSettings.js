import { useState, useEffect } from 'react';
import { api } from '../utils/api.js';
import { route } from 'ziggy-js';

/**
 * Trigger-mode changes save immediately, matching how voice-model selection
 * works on the Voice page — no separate batched "Save" button here.
 */
export default function useDiscordSettings(addToast, assistantId) {
	const [guilds, setGuilds] = useState([]);
	const [dms, setDms] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [discoveryError, setDiscoveryError] = useState(null);

	useEffect(() => {
		const load = async () => {
			try {
				const res = await api.get(route('discord.discovery', { assistant: assistantId }));
				const data = await res.json();

				setGuilds(data.guilds ?? []);
				setDms(data.dms ?? []);
				setDiscoveryError(data.guilds?.length ? null : (data.message ?? null));
			} catch (e) {
				addToast('Failed to load Discord settings', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		void load();
	}, [assistantId]);

	const setChannelTrigger = async (guild, channel, triggerMode) => {
		const configuredChannels = guilds
			.flatMap((g) => g.channels.map((c) => ({ ...c, guildId: g.id, guildName: g.name })))
			.filter((c) => c.trigger_mode && c.trigger_mode !== 'off' && c.id !== channel.id)
			.map((c) => ({
				guild_id: c.guildId,
				guild_name: c.guildName,
				channel_id: c.id,
				channel_name: c.name,
				trigger_mode: c.trigger_mode,
			}));

		const nextChannels = triggerMode === 'off'
			? configuredChannels
			: [
				...configuredChannels,
				{
					guild_id: guild.id,
					guild_name: guild.name,
					channel_id: channel.id,
					channel_name: channel.name,
					trigger_mode: triggerMode,
				},
			];

		try {
			const res = await api.put(route('settings.updateDiscord', { assistant: assistantId }), { channels: nextChannels });
			if (!res.ok) throw new Error('Save failed');

			setGuilds((prev) =>
				prev.map((g) =>
					g.id !== guild.id
						? g
						: {
							...g,
							channels: g.channels.map((c) =>
								c.id === channel.id ? { ...c, trigger_mode: triggerMode } : c
							),
						}
				)
			);
			addToast('Channel updated', 'success');
		} catch {
			addToast('Failed to update channel', 'error');
		}
	};

	return { guilds, dms, isLoading, discoveryError, setChannelTrigger };
}
