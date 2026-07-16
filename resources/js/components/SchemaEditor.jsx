import { useState } from 'react';
import SchemaFieldEditor from './SchemaFieldEditor.jsx';

const emptyField = () => ({
	uid: crypto.randomUUID(),
	name: '',
	type: 'string',
	required: false,
	section: 'primary',
});

export default function SchemaEditor({ schema, onChange, nested = false }) {
	const [mode, setMode] = useState('form');
	const [jsonValue, setJsonValue] = useState('');
	const [jsonError, setJsonError] = useState(null);

	const fields = Array.isArray(schema) ? schema : [];

	const handleSwitchMode = (next) => {
		if (next === 'json') {
			setJsonValue(JSON.stringify(stripUids(fields), null, 2));
			setJsonError(null);
		}
		setMode(next);
	};

	const handleJsonChange = (raw) => {
		setJsonValue(raw);
		try {
			const parsed = JSON.parse(raw);
			if (!Array.isArray(parsed)) throw new Error();
			setJsonError(null);
			onChange(parsed);
		} catch {
			setJsonError('Must be a valid JSON array');
		}
	};

	const addField = () => onChange([...fields, emptyField()]);

	const updateField = (index, updated) =>
		onChange(fields.map((f, i) => (i === index ? updated : f)));

	const removeField = (index) =>
		onChange(fields.filter((_, i) => i !== index));

	return (
		<div className="space-y-2">
			{!nested && (
				<div className="flex justify-end gap-1">
					{['form', 'json'].map((m) => (
						<button
							key={m}
							onClick={() => handleSwitchMode(m)}
							className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border transition-colors cursor-pointer ${
								mode === m
									? 'border-accent text-accent bg-accent/10'
									: 'border-line-1 text-fg-3 hover:border-fg-3'
							}`}
						>
							{m === 'form' ? 'Form' : 'JSON'}
						</button>
					))}
				</div>
			)}

			{mode === 'json' && !nested ? (
				<div>
					<textarea
						value={jsonValue}
						onChange={(e) => handleJsonChange(e.target.value)}
						rows={8}
						className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none font-mono text-xs"
					/>
					{jsonError && (
						<p className="text-danger text-[0.65rem] tracking-[0.05em] mt-1">{jsonError}</p>
					)}
				</div>
			) : (
				<div className="space-y-2">
					{fields.map((field, i) => (
						<SchemaFieldEditor
							key={field.uid ?? i}
							field={field}
							onChange={(updated) => updateField(i, updated)}
							onRemove={() => removeField(i)}
							showSection={!nested}
						/>
					))}
					<button
						onClick={addField}
						className="w-full text-[0.65rem] tracking-[0.1em] py-2 border border-dashed border-line-1 text-fg-3 cursor-pointer hover:border-accent/50 hover:text-accent transition-colors"
					>
						+ ADD FIELD
					</button>
				</div>
			)}
		</div>
	);
}

function stripUids(fields) {
	return fields.map(({ uid, ...rest }) => ({
		...rest,
		...(rest.children ? { children: stripUids(rest.children) } : {}),
	}));
}
