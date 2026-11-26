import { FIELD_INPUT } from '../utils/formFieldStyles.js';

/** Picks one of the user's archive entries, grouped by archive. */
export default function ArchiveEntrySelect({ entries, value, onChange, ariaLabel }) {
	const archives = [...new Set(entries.map((entry) => entry.archiveName))];

	return (
		<select value={value ?? ''} onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))} className={FIELD_INPUT} aria-label={ariaLabel}>
			<option value="">— no entry —</option>
			{archives.map((archive) => (
				<optgroup key={archive} label={archive}>
					{entries.filter((entry) => entry.archiveName === archive).map((entry) => <option key={entry.id} value={entry.id}>{entry.title}</option>)}
				</optgroup>
			))}
		</select>
	);
}
