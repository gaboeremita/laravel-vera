import { useState, useEffect } from 'react';
import { api } from '../utils/api.js';
import {route} from "ziggy-js";

function stripSchemaUids(fields) {
	return fields.map(({ uid, ...rest }) => ({
		...rest,
		...(Array.isArray(rest.children) ? { children: stripSchemaUids(rest.children) } : {}),
	}));
}

export default function useProviders(addToast, assistantId) {
	const [providers, setProviders] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [deleteTarget, setDeleteTarget] = useState(null);
	const [activeModelId, setActiveModelId] = useState(null);

	useEffect(() => {
		const load = async () => {
			try {
				const [providersRes, settingsRes] = await Promise.all([
					api.get(route('ai-providers.index')),
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
					models: (p.models ?? []).map((m) => ({
						...m,
						collapsed: true,
						saving: false,
					})),
				})));

				setActiveModelId(settings.ai_model_id ?? null);
			} catch (e) {
				addToast('Failed to load providers', 'error');
			} finally {
				setIsLoading(false);
			}
		};
		void load();
	}, []);

	/* ── Provider operations ── */

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
				format: 'generic',
				prompt: '',
				config_schema: '',
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
				prompt: provider.prompt || null,
			};

			if (typeof provider.config_schema === 'string' && provider.config_schema.trim()) {
				payload.config_schema = JSON.parse(provider.config_schema);
			} else if (Array.isArray(provider.config_schema)) {
				payload.config_schema = stripSchemaUids(provider.config_schema);
			}

			if (provider.api_key) {
				payload.api_key = provider.api_key;
			}

			let res;
			if (provider.id) {
				res = await api.patch(route('ai-providers.update', { id: provider.id }), payload);
			} else {
				payload.api_key = provider.api_key || '';
				res = await api.post(route('ai-providers.store'), payload);
			}

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			const data = await res.json();

			setProviders((prev) =>
				prev.map((p, i) =>
					i === index
						? {
							...p,
							...data,
							api_key: '',
							hasKey: true,
							collapsed: p.collapsed,
							saving: false,
							models: p.models,
						}
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
				const res = await api.delete(route('ai-providers.destroy', { id: provider.id }));
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

	/* ── Model operations ── */

	const updateModel = (providerIndex, modelIndex, field, value) => {
		setProviders((prev) =>
			prev.map((p, pi) =>
				pi === providerIndex
					? {
						...p,
						models: p.models.map((m, mi) =>
							mi === modelIndex ? { ...m, [field]: value } : m
						),
					}
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
								thinking_key: '',
								prompt: '',
								config: '',
								additional_config: '',
								collapsed: false,
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
				thinking_key: model.thinking_key || null,
				prompt: model.prompt || null,
			};

			if (typeof model.config === 'string' && model.config.trim()) {
				payload.config = JSON.parse(model.config);
			} else if (typeof model.config === 'object') {
				payload.config = model.config;
			}

			if (typeof model.additional_config === 'string' && model.additional_config.trim()) {
				payload.additional_config = JSON.parse(model.additional_config);
			} else if (typeof model.additional_config === 'object' && model.additional_config) {
				payload.additional_config = model.additional_config;
			}

			let res;
			if (model.id) {
				res = await api.patch(
					route('ai-models.update', { provider: provider.id, model: model.id }),
					payload
				);
			} else {
				res = await api.post(
					route('ai-models.store', { provider: provider.id }),
					payload
				);
			}

			if (!res.ok) {
				const error = await res.json().catch(() => ({}));
				throw new Error(error.message || 'Save failed');
			}

			const data = await res.json();
			updateModel(providerIndex, modelIndex, 'id', data.id);
			updateModel(providerIndex, modelIndex, 'saving', false);
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
					route('ai-models.destroy', { provider: provider.id, model: model.id })
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

	const selectModel = async (modelId) => {
		try {
			await api.put(route('settings.selectModel', { assistant: assistantId }), {
				ai_model_id: modelId,
			});
			setActiveModelId(modelId);
			addToast('Model selected', 'success');
		} catch (e) {
			addToast('Failed to select model', 'error');
		}
	};

	return {
		providers,
		isLoading,
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
		activeModelId,
		selectModel,
	};
}