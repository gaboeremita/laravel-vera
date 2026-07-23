import { useState } from 'react';
import PromptEditor from './PromptEditor.jsx';

/**
 * Manual/Paste-JSON toggle around a usePromptTree instance — same switcher PromptPage uses,
 * factored out so any prompt tree (not just the assistant's own) gets the same editing UX.
 */
export default function PromptTreeEditor({ promptTree }) {
	const [mode, setMode] = useState('manual');
	const [json, setJson] = useState('');
	const [jsonError, setJsonError] = useState(null);

	const handleSwitchMode = (nextMode) => {
		if (nextMode === 'json') {
			setJson(JSON.stringify(promptTree.sections ?? {}, null, 2));
			setJsonError(null);
		}
		setMode(nextMode);
	};

	return (
		<div>
			<div className="flex justify-end mb-2">
				<div className="flex gap-1">
					{['manual', 'json'].map((m) => (
						<button
							key={m}
							onClick={() => handleSwitchMode(m)}
							className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border transition-colors cursor-pointer ${
								mode === m
									? 'border-accent text-accent bg-accent/10'
									: 'border-line-1 text-fg-3 hover:border-fg-3'
							}`}
						>
							{m === 'manual' ? 'Manual' : 'Paste JSON'}
						</button>
					))}
				</div>
			</div>

			{mode === 'manual' ? (
				<PromptEditor {...promptTree} />
			) : (
				<div>
					<textarea
						value={json}
						onChange={(e) => {
							setJson(e.target.value);
							try {
								JSON.parse(e.target.value);
								setJsonError(null);
							} catch {
								setJsonError('Invalid JSON');
							}
						}}
						rows={10}
						className={`w-full bg-bg-1 border text-accent text-sm px-3 py-2 outline-none transition-colors resize-y font-mono text-xs ${
							jsonError ? 'border-danger' : 'border-line-1 focus:border-accent/50'
						}`}
					/>
					{jsonError && (
						<p className="text-danger text-[0.65rem] mt-1">{jsonError}</p>
					)}
					<div className="flex justify-end mt-3">
						<button
							onClick={() => promptTree.saveFromJson(json)}
							disabled={!!jsonError || promptTree.isSaving}
							className={`text-[0.75rem] tracking-[0.1em] px-6 py-2 transition-colors ${
								jsonError || promptTree.isSaving
									? 'bg-bg-3 text-fg-3 cursor-default'
									: 'button-success cursor-pointer'
							}`}
						>
							{promptTree.isSaving ? 'SAVING...' : 'SAVE PROMPT'}
						</button>
					</div>
				</div>
			)}
		</div>
	);
}
