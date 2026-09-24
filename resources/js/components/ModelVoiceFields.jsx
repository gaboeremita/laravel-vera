import { useEffect, useId, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';

const LABEL = 'text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1';
const FIELD = 'w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors';

/**
 * LLM model, voice model and voice for a character, chosen from the
 * providers set up on the AI Providers and Voice pages.
 *
 * @param {{ aiModelId: ?number, ttsModelId: ?number, ttsVoice: ?string }} value
 * @param {function} onChange - (changes) => void, with any of aiModelId, ttsModelId, ttsVoice
 * @param {function} addToast
 */
export default function ModelVoiceFields({ value, onChange, addToast }) {
	const voiceListId = useId();
	const [aiProviders, setAiProviders] = useState([]);
	const [voiceProviders, setVoiceProviders] = useState([]);
	const [voiceDraft, setVoiceDraft] = useState(value.ttsVoice ?? '');
	const [syncedVoice, setSyncedVoice] = useState(value.ttsVoice);

	if (syncedVoice !== value.ttsVoice) {
		setSyncedVoice(value.ttsVoice);
		setVoiceDraft(value.ttsVoice ?? '');
	}

	useEffect(() => {
		const load = async () => {
			try {
				const [aiResponse, voiceResponse] = await Promise.all([
					api.get(route('ai-providers.index')),
					api.get(route('voice-providers.index')),
				]);
				if (!aiResponse.ok || !voiceResponse.ok) throw new Error('Failed to load models');
				setAiProviders(await aiResponse.json());
				setVoiceProviders(await voiceResponse.json());
			} catch (error) {
				addToast(error.message || 'Failed to load models', 'error');
			}
		};
		void load();
	}, [addToast]);

	const voiceModel = voiceProviders.flatMap((provider) => provider.models ?? []).find((model) => model.id === value.ttsModelId);
	const voices = Array.isArray(voiceModel?.voices) ? voiceModel.voices : [];
	const commitVoice = () => {
		const voice = voiceDraft.trim() || null;
		if (voice !== (value.ttsVoice ?? null)) onChange({ ttsVoice: voice });
	};

	return (
		<>
			<div>
				<label className={LABEL}>LLM Model <span className="text-fg-3 normal-case">(living in a world needs a model that supports tool calling)</span></label>
				<select
					value={value.aiModelId ?? ''}
					onChange={(event) => onChange({ aiModelId: event.target.value ? Number(event.target.value) : null })}
					className={FIELD}
				>
					<option value="">— Default model —</option>
					{aiProviders.map((provider) => (
						<optgroup key={provider.id} label={provider.name}>
							{(provider.models ?? []).map((model) => (
								<option key={model.id} value={model.id}>{model.name}{model.supports_tools ? ' · tools' : ''}</option>
							))}
						</optgroup>
					))}
				</select>
			</div>

			<div>
				<label className={LABEL}>Voice Model</label>
				<select
					value={value.ttsModelId ?? ''}
					onChange={(event) => onChange({ ttsModelId: event.target.value ? Number(event.target.value) : null, ttsVoice: null })}
					className={FIELD}
				>
					<option value="">— Default voice —</option>
					{voiceProviders.map((provider) => (
						<optgroup key={provider.id} label={provider.name}>
							{(provider.models ?? []).map((model) => (
								<option key={model.id} value={model.id}>{model.name}</option>
							))}
						</optgroup>
					))}
				</select>
			</div>

			{value.ttsModelId && (
				<div>
					<label className={LABEL}>Voice</label>
					<input
						type="text"
						list={voiceListId}
						value={voiceDraft}
						onChange={(event) => setVoiceDraft(event.target.value)}
						onBlur={commitVoice}
						onKeyDown={(event) => { if (event.key === 'Enter') commitVoice(); }}
						placeholder={voices[0] ? `e.g. ${voices[0]}` : 'Voice name'}
						className={FIELD}
					/>
					<datalist id={voiceListId}>
						{voices.map((voice) => <option key={voice} value={voice} />)}
					</datalist>
				</div>
			)}
		</>
	);
}
