import { useNavigate, useOutletContext } from 'react-router-dom';
import Header from '../components/Header.jsx';
import PromptEditor from '../components/PromptEditor.jsx';
import usePrompt from '../hooks/usePrompt.js';

export default function PromptPage() {
	const navigate = useNavigate();
	const { assistantId, addToast } = useOutletContext();

	const prompt = usePrompt(assistantId, addToast);

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
				<PromptEditor {...prompt} />
			</div>
		</>
	);
}