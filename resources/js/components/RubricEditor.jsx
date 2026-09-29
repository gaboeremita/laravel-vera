import { useState } from 'react';
import { FIELD_INPUT, FIELD_LABEL } from '../utils/formFieldStyles.js';

const HINT = 'normal-case text-fg-3';
const SMALL_BUTTON = 'text-[0.65rem] tracking-[0.1em] px-2 py-1 border border-dashed border-line-1 text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer';

/** The guidance, dimensions and optional tiers an ending is judged by; shared by quests and campaigns. */
export default function RubricEditor({ value, onChange, errorsAt }) {
	const [newTier, setNewTier] = useState('');
	const rubric = { guidance: '', dimensions: [], tiers: [], ...value };
	const update = (field, next) => onChange({ ...rubric, [field]: next });
	const updateDimension = (index, field, next) => update('dimensions', rubric.dimensions.map((dimension, position) => (position === index ? { ...dimension, [field]: next } : dimension)));

	const addTier = () => {
		const tier = newTier.trim();
		if (tier === '' || rubric.tiers.includes(tier)) return;
		update('tiers', [...rubric.tiers, tier]);
		setNewTier('');
	};

	return (
		<div className="space-y-3">
			<div>
				<label className={FIELD_LABEL}>Guidance <span className={HINT}>(how to judge the ending)</span></label>
				<textarea value={rubric.guidance} onChange={(event) => update('guidance', event.target.value)} rows={2} placeholder="Judge how honestly and kindly the player treated the miller." className={`${FIELD_INPUT} resize-none placeholder:text-fg-3/60`} />
			</div>
			<div>
				<p className={FIELD_LABEL}>Dimensions <span className={HINT}>(each scored from 1 to 10, with a reason)</span></p>
				<div className="space-y-2">
					{rubric.dimensions.map((dimension, index) => (
						<div key={index} className="grid grid-cols-[10rem_1fr_auto] gap-2 items-start hud-enter-fade">
							<input value={dimension.name} onChange={(event) => updateDimension(index, 'name', event.target.value)} placeholder="honesty" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} aria-label="Dimension name" />
							<input value={dimension.description ?? ''} onChange={(event) => updateDimension(index, 'description', event.target.value)} placeholder="Did the player tell the truth?" className={`${FIELD_INPUT} placeholder:text-fg-3/60`} aria-label="Dimension description" />
							<button type="button" onClick={() => update('dimensions', rubric.dimensions.filter((_, position) => position !== index))} className="text-fg-3 hover:text-danger text-xs px-1 py-2 cursor-pointer transition-colors" aria-label="Remove dimension">✕</button>
						</div>
					))}
					<button type="button" onClick={() => update('dimensions', [...rubric.dimensions, { name: '', description: '' }])} className={SMALL_BUTTON}>+ DIMENSION</button>
				</div>
				<FieldErrors messages={errorsAt('rubric.dimensions', true)} />
			</div>
			<div>
				<p className={FIELD_LABEL}>Tiers <span className={HINT}>(optional; the ending picks one, or describes itself freely)</span></p>
				<div className="flex flex-wrap items-center gap-2">
					{rubric.tiers.map((tier) => (
						<span key={tier} className="flex items-center gap-1.5 border border-accent/40 bg-accent/5 px-2 py-1 text-accent text-xs">
							{tier}
							<button type="button" onClick={() => update('tiers', rubric.tiers.filter((candidate) => candidate !== tier))} className="text-fg-3 hover:text-danger cursor-pointer" aria-label={`Remove ${tier}`}>✕</button>
						</span>
					))}
					<input value={newTier} onChange={(event) => setNewTier(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); addTier(); } }} placeholder="bittersweet" className="bg-bg-1 border border-line-1 text-accent text-xs px-2 py-1 outline-none focus:border-accent/50 w-32 placeholder:text-fg-3/60" aria-label="New tier" />
					<button type="button" onClick={addTier} className={SMALL_BUTTON}>+</button>
				</div>
				<FieldErrors messages={errorsAt('rubric.tiers', true)} />
			</div>
		</div>
	);
}

/** Server messages for one field, shown under it. */
export function FieldErrors({ messages }) {
	if (!messages?.length) return null;
	return (
		<div className="mt-1 space-y-0.5">
			{messages.map((message) => <p key={message} className="text-danger text-[0.65rem] tracking-[0.05em]">{message}</p>)}
		</div>
	);
}
