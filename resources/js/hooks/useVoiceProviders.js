import { useState, useEffect } from 'react';
import { api } from '../utils/api.js';
import { route } from 'ziggy-js';

export default function useVoiceProviders(addToast, assistantId) {
	const [providers, setProviders] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [activeModelId, setActiveModelId] = useState(null);
	const [activeVoice, setActiveVoice] = useState(null);
	const [deleteTarget, setDeleteTarget] = useState(null);

	useEffect(() => {
		const load = async () => {
			try {
				const [providersRes, settingsRes] = await Promise.all([
					api.get(route('voice-providers.index')),
					api.get(route('settings.show', { assistant: assistantId })),
				]);

				const data = await providersRes.json();
				const settings = await settingsRes.json();

				setProviders(data.map((p) => ({
					...p,
					collapsed: true,
					api_key: '',
					hasKey: !!p.has_key,
					saving: false,
					models: (p.models ?? []).map((m) => ({ ...m, saving: false })),
				})));
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

	/* ── Provider CRUD ── */

	const updateProvider = (index, field, value) => {
		setProviders((prev) =>
			prev.map((p, i) => (i === index ? { ...p, [field]: value } : p))
		);
	};

	const addProvider = () => {
		setProviders((prev) => [
			...prev,
			{
				uid: crypto.randomUUID(),
				name: '',
				url: '',
				api_key: '',
				format: 'openai_compatible',
				instructions: '',
				collapsed: false,
				saving: false,
				models: [],
			},
		]);
	};

	const saveProvider = async (index) => {
		const provider = providers[index];
		updateProvider(index, 'saving', true);

		try {
			const payload = {
				name: provider.name,
				url: provider.url,
				format: provider.format,
				instructions: provider.instructions || null,
			};

			if (provider.api_key) {
				payload.api_key = provider.api_key;
			}

			let res;
			if (provider.id) {
				res = await api.put(route('voice-providers.update', { id: provider.id }), payload);
			} else {
				res = await api.post(route('voice-providers.store'), payload);
			}

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			const data = await res.json();

			setProviders((prev) =>
				prev.map((p, i) =>
					i === index
						? { ...p, ...data, api_key: '', hasKey: !!data.has_key, collapsed: p.collapsed, saving: false, models: p.models }
						: p
				)
			);

			addToast('Provider saved', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to save provider', 'error');
			updateProvider(index, 'saving', false);
		}
	};

	const deleteProvider = async () => {
		if (!deleteTarget || deleteTarget.type !== 'provider') return;
		const { providerIndex } = deleteTarget;
		const provider = providers[providerIndex];

		try {
			if (provider.id) {
				const res = await api.delete(route('voice-providers.destroy', { id: provider.id }));
				if (!res.ok) throw new Error('Delete failed');
			}
			setProviders((prev) => prev.filter((_, i) => i !== providerIndex));
			addToast('Provider deleted', 'success');
		} catch (e) {
			addToast('Failed to delete provider', 'error');
		} finally {
			setDeleteTarget(null);
		}
	};

	/* ── Model CRUD ── */

	const updateModel = (providerIndex, modelIndex, field, value) => {
		setProviders((prev) =>
			prev.map((p, pi) =>
				pi === providerIndex
					? { ...p, models: p.models.map((m, mi) => (mi === modelIndex ? { ...m, [field]: value } : m)) }
					: p
			)
		);
	};

	const addModel = (providerIndex) => {
		setProviders((prev) =>
			prev.map((p, i) =>
				i === providerIndex
					? {
						...p,
						models: [
							...p.models,
							{
								uid: crypto.randomUUID(),
								name: '',
								endpoint: '',
								voices: [],
								config: '',
								saving: false,
							},
						],
					}
					: p
			)
		);
	};

	const saveModel = async (providerIndex, modelIndex) => {
		const provider = providers[providerIndex];
		const model = provider.models[modelIndex];
		updateModel(providerIndex, modelIndex, 'saving', true);

		try {
			const payload = {
				name: model.name,
				endpoint: model.endpoint,
				voices: Array.isArray(model.voices) ? model.voices : [],
			};

			if (typeof model.config === 'string' && model.config.trim()) {
				payload.config = JSON.parse(model.config);
			} else if (typeof model.config === 'object' && model.config) {
				payload.config = model.config;
			}

			let res;
			if (model.id) {
				res = await api.put(
					route('voice-models.update', { provider: provider.id, model: model.id }),
					payload
				);
			} else {
				res = await api.post(
					route('voice-models.store', { provider: provider.id }),
					payload
				);
			}

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			const data = await res.json();
			setProviders((prev) =>
				prev.map((p, pi) =>
					pi === providerIndex
						? { ...p, models: p.models.map((m, mi) => (mi === modelIndex ? { ...m, ...data, saving: false } : m)) }
						: p
				)
			);
			addToast('Model saved', 'success');
		} catch (e) {
			addToast(e.message || 'Failed to save model', 'error');
			updateModel(providerIndex, modelIndex, 'saving', false);
		}
	};

	const deleteModel = async () => {
		if (!deleteTarget || deleteTarget.type !== 'model') return;
		const { providerIndex, modelIndex } = deleteTarget;
		const provider = providers[providerIndex];
		const model = provider.models[modelIndex];

		try {
			if (model.id) {
				const res = await api.delete(
					route('voice-models.destroy', { provider: provider.id, model: model.id })
				);
				if (!res.ok) throw new Error('Delete failed');
			}
			setProviders((prev) =>
				prev.map((p, pi) =>
					pi === providerIndex
						? { ...p, models: p.models.filter((_, mi) => mi !== modelIndex) }
						: p
				)
			);
			addToast('Model deleted', 'success');
		} catch (e) {
			addToast('Failed to delete model', 'error');
		} finally {
			setDeleteTarget(null);
		}
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
		deleteTarget,
		setDeleteTarget,
		addProvider,
		updateProvider,
		saveProvider,
		deleteProvider,
		addModel,
		updateModel,
		saveModel,
		deleteModel,
	};
}
