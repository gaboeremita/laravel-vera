import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

/** Picks which of the user's models narrates objects and items in this world. */
export default function NarratorModelSelect({ value, onChange }) {
	const [providers, setProviders] = useState([]);

	useEffect(() => {
		let active = true;
		const load = async () => {
			const response = await api.get(route('ai-providers.index'));
			if (!response.ok) return;
			const loaded = await response.json();
			if (active) setProviders(loaded);
		};
		void load();
		return () => { active = false; };
	}, []);

	return (
		<div>
			<label className={FIELD_LABEL}>Narrator model <span className="normal-case text-fg-3">(judges and narrates objects and items; needs tool calling)</span></label>
			<select value={value ?? ''} onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))} className={FIELD_INPUT}>
				<option value="">— the app's default model —</option>
				{providers.map((provider) => (
					<optgroup key={provider.id} label={provider.name}>
						{(provider.models ?? []).map((model) => (
							<option key={model.id} value={model.id} disabled={!model.supports_tools}>
								{model.name}{model.supports_tools ? '' : ' (no tool calling)'}
							</option>
						))}
					</optgroup>
				))}
			</select>
		</div>
	);
}
