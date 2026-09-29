import { FIELD_INPUT } from '../utils/formFieldStyles.js';

/** Picks one of the world's facts, grouped by the resident who holds it. */
export default function FactSelect({ facts, value, onChange }) {
	const holders = [...new Set(facts.map((fact) => fact.holderName))];

	return (
		<select value={value ?? ''} onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))} className={FIELD_INPUT} aria-label="Reveals fact">
			<option value="">— no fact —</option>
			{holders.map((holder) => (
				<optgroup key={holder} label={holder}>
					{facts.filter((fact) => fact.holderName === holder).map((fact) => <option key={fact.id} value={fact.id}>{fact.topic}</option>)}
				</optgroup>
			))}
		</select>
	);
}
