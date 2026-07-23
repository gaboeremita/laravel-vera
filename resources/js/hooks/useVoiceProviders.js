import { useState, useEffect } from 'react';
import { api } from '../utils/api.js';
import { route } from 'ziggy-js';

export default function useVoiceProviders(addToast, assistantId) {
	const [providers, setProviders] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [activeModelId, setActiveModelId] = useState(null);
	const [activeVoice, setActiveVoice] = useState(null);

	useEffect(() => {
		const load = async () => {
			try {
				const [providersRes, settingsRes] = await Promise.all([
					api.get(route('voice-providers.index')),
					api.get(route('settings.show', { assistant: assistantId })),
				]);

				const data = await providersRes.json();
				const settings = await settingsRes.json();

				setProviders(data.map((p) => ({ ...p, collapsed: true })));
				setActiveModelId(settings.tts_model_id ?? null);
				setActiveVoice(settings.tts_voice ?? null);
			} catch (e) {
				addToast('Failed to load voice providers', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		void load();
	}, [assistantId]);

	const toggleProvider = (index) => {
		setProviders((prev) =>
			prev.map((p, i) => (i === index ? { ...p, collapsed: !p.collapsed } : p))
		);
	};

	// Picking a voice for a model that isn't active yet activates it in the same action —
	// forcing a separate SELECT click before the voice dropdown works was pointless friction.
	const chooseVoice = async (modelId, voice) => {
		try {
			if (modelId !== activeModelId) {
				await api.put(route('settings.selectVoiceModel', { assistant: assistantId }), {
					tts_model_id: modelId,
				});
				setActiveModelId(modelId);
			}

			await api.put(route('settings.updateVoice', { assistant: assistantId }), {
				tts_voice: voice,
			});
			setActiveVoice(voice);

			addToast('Voice selected', 'success');
		} catch (e) {
			addToast('Failed to select voice', 'error');
		}
	};

	const deactivateModel = async () => {
		try {
			await api.put(route('settings.selectVoiceModel', { assistant: assistantId }), {
				tts_model_id: null,
			});
			await api.put(route('settings.updateVoice', { assistant: assistantId }), {
				tts_voice: null,
			});
			setActiveModelId(null);
			setActiveVoice(null);
			addToast('Voice model deselected', 'success');
		} catch (e) {
			addToast('Failed to deselect voice model', 'error');
		}
	};

	return {
		providers,
		isLoading,
		toggleProvider,
		activeModelId,
		activeVoice,
		chooseVoice,
		deactivateModel,
	};
}
