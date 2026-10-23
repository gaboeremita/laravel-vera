import { FIELD_LABEL, FIELD_INPUT } from '../utils/formFieldStyles.js';
import { emptySentimentRow } from '../utils/sentiments.js';

const SMALL_BUTTON = 'text-[0.65rem] tracking-[0.1em] px-2 py-1 border border-dashed border-line-1 text-fg-3 hover:text-accent hover:border-accent/50 transition-colors cursor-pointer';
const ICON_BUTTON = 'text-fg-3 text-xs px-1 cursor-pointer transition-colors disabled:opacity-30 disabled:cursor-default';

export default function SentimentsEditor({ value, onChange, hint }) {
	const update = (key, field, fieldValue) => onChange(value.map((row) => (row.key === key ? { ...row, [field]: fieldValue } : row)));
	const move = (index, offset) => {
		const next = [...value];
		[next[index], next[index + offset]] = [next[index + offset], next[index]];
		onChange(next);
	};

	return (
		<div className="space-y-4">
			<p className="text-fg-3 text-xs">{hint}</p>
			{value.map((row, index) => (
				<div key={row.key} className="border-l-2 border-accent/40 pl-3 space-y-2">
					<div className="flex items-end gap-2">
						<div className="flex-1">
							<label className={FIELD_LABEL}>Name</label>
							<input value={row.name} onChange={(event) => update(row.key, 'name', event.target.value)} className={FIELD_INPUT} required />
						</div>
						<button type="button" onClick={() => move(index, -1)} disabled={index === 0} className={`${ICON_BUTTON} hover:text-accent pb-2`} aria-label="Move up">▲</button>
						<button type="button" onClick={() => move(index, 1)} disabled={index === value.length - 1} className={`${ICON_BUTTON} hover:text-accent pb-2`} aria-label="Move down">▼</button>
						<button type="button" onClick={() => onChange(value.filter((candidate) => candidate.key !== row.key))} className={`${ICON_BUTTON} hover:text-danger pb-2`} aria-label="Remove">✕</button>
					</div>
					<div>
						<label className={FIELD_LABEL}>Description</label>
						<textarea value={row.description} onChange={(event) => update(row.key, 'description', event.target.value)} rows={2} placeholder="-10 is expecting betrayal at every turn; 10 is trusting them with your life." className={`${FIELD_INPUT} resize-none`} required />
					</div>
				</div>
			))}
			<button type="button" onClick={() => onChange([...value, emptySentimentRow()])} className={SMALL_BUTTON}>+ ADD SENTIMENT</button>
		</div>
	);
}
