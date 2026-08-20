import { useState } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import Header from '../components/Header.jsx';
import PromptEditor from '../components/PromptEditor.jsx';
import usePrompt from '../hooks/usePrompt.js';

export default function PromptPage() {
	const navigate = useNavigate();
	const { assistantId, addToast } = useOutletContext();

	const prompt = usePrompt(assistantId, addToast);
	const [promptMode, setPromptMode] = useState('manual');
	const [promptJson, setPromptJson] = useState('');
	const [promptJsonError, setPromptJsonError] = useState(null);

	const handleSwitchMode = (mode) => {
		if (mode === 'json') {
			setPromptJson(JSON.stringify(prompt.sections ?? {}, null, 2));
			setPromptJsonError(null);
		}
		setPromptMode(mode);
	};

	if (prompt.isLoading) {
		return (
			<>
				<Header settingsPath={`/assistants/${assistantId}/settings`}
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
					actions={
						<button onClick={() => navigate(-1)} className="button-primary">
							← PREVIOUS PAGE
						</button>
					}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Prompt</span>
				</Header>
				<div className="flex-1 p-5">
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				</div>
			</>
		);
	}

	return (
		<>
			<Header settingsPath={`/assistants/${assistantId}/settings`}
				status={{
					label: prompt.isSaving ? 'SAVING' : 'WAITING',
					color: prompt.isSaving ? 'text-warning' : 'text-info',
					dot: '●',
					blink: prompt.isSaving,
				}}
				counter={prompt.sections ? `SECTIONS: ${Object.entries(prompt.sections).length}` : null}
				actions={
					<div className="flex items-center gap-3">
						<button onClick={() => navigate(-1)} className="button-primary">
							← PREVIOUS PAGE
						</button>
						{prompt.sections && (
							<button
								onClick={() => prompt.destroy()}
								className="text-danger text-[0.7rem] tracking-[0.1em] cursor-pointer hover:text-danger transition-colors"
							>
								DELETE PROMPT
							</button>
						)}
					</div>
				}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Prompt</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar">
				<div className="flex justify-end mb-4">
					<div className="flex gap-1">
						{['manual', 'json'].map((mode) => (
							<button
								key={mode}
								onClick={() => handleSwitchMode(mode)}
								className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border transition-colors cursor-pointer ${
									promptMode === mode
										? 'border-accent text-accent bg-accent/10'
										: 'border-line-1 text-fg-3 hover:border-fg-3'
								}`}
							>
								{mode === 'manual' ? 'Manual' : 'Paste JSON'}
							</button>
						))}
					</div>
				</div>

				{promptMode === 'manual' ? (
					<PromptEditor {...prompt} />
				) : (
					<div>
						<textarea
							value={promptJson}
							onChange={(e) => {
								setPromptJson(e.target.value);
								try {
									JSON.parse(e.target.value);
									setPromptJsonError(null);
								} catch {
									setPromptJsonError('Invalid JSON');
								}
							}}
							rows={20}
							className={`w-full bg-bg-1 border text-accent text-sm px-3 py-2 outline-none transition-colors resize-y font-mono ${
								promptJsonError ? 'border-danger' : 'border-line-1 focus:border-accent/50'
							}`}
						/>
						{promptJsonError && (
							<p className="text-danger text-[0.65rem] mt-1">{promptJsonError}</p>
						)}
						<div className="flex justify-end mt-3">
							<button
								onClick={() => prompt.saveFromJson(promptJson)}
								disabled={!!promptJsonError || prompt.isSaving}
								className={`text-[0.75rem] tracking-[0.1em] px-6 py-2 transition-colors ${
									promptJsonError || prompt.isSaving
										? 'bg-bg-3 text-fg-3 cursor-default'
										: 'button-success cursor-pointer'
								}`}
							>
								{prompt.isSaving ? 'SAVING...' : 'SAVE PROMPT'}
							</button>
						</div>
					</div>
				)}
			</div>
		</>
	);
}