const CHECKBOXES = [
	{ key: 'markTerms', label: 'Mark matched terms in my message' },
	{ key: 'swapInvariant', label: 'Swap invariant terms back exactly' },
	{ key: 'highlightMissing', label: 'Highlight missing target terms' },
];

export default function TermRuleSettings({ sections, value, onChange }) {
	const textSections = Object.entries(sections ?? {})
		.filter(([, sectionValue]) => typeof sectionValue === 'string')
		.map(([key]) => key);

	return (
		<div className="space-y-2">
			<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
				Term rules section
			</label>
			<select
				value={value.section ?? ''}
				onChange={(e) => onChange({ ...value, section: e.target.value || null })}
				className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
			>
				<option value="">— None —</option>
				{textSections.map((key) => (
					<option key={key} value={key}>{key}</option>
				))}
			</select>
			{CHECKBOXES.map(({ key, label }) => (
				<label key={key} className="flex items-center gap-2 text-fg-2 text-sm cursor-pointer">
					<input
						type="checkbox"
						checked={Boolean(value[key])}
						onChange={(e) => onChange({ ...value, [key]: e.target.checked })}
						className="accent-accent"
					/>
					{label}
				</label>
			))}
		</div>
	);
}
