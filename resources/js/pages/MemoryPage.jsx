import { useParams, useNavigate, useOutletContext } from 'react-router-dom';
import Header from '../components/Header.jsx';
import Toggle from '../components/common/Toggle.jsx';
import AssistantMemoryPromptEditor from '../components/AssistantMemoryPromptEditor.jsx';
import useConversationMemory from '../hooks/useConversationMemory.js';

export default function MemoryPage() {
	const { id } = useParams();
	const navigate = useNavigate();
	const { addToast, assistantId } = useOutletContext();

	const {
		memory,
		setMemory,
		pendingCount,
		isLoading,
		isSaving,
		isSummarizing,
		isLocked,
		isUnlocking,
		autoSummarizeEnabled,
		isTogglingAutoSummarize,
		save,
		summarizeSinceLast,
		summarizeAsFarAsPossible,
		forceUnlock,
		toggleAutoSummarize,
	} = useConversationMemory(addToast, assistantId, id);

	const backToChat = () => navigate(`/assistants/${assistantId}/conversations/${id}`);

	if (isLoading) {
		return (
			<>
				<Header onBack={backToChat}
					status={{ label: 'LOADING', color: 'text-warning', dot: '●', blink: true }}
				>
					<span className="text-fg-2 text-sm tracking-[0.05em]">Memory</span>
				</Header>
				<div className="flex-1 p-5">
					<span className="text-fg-3 text-sm cursor-effect">Loading...</span>
				</div>
			</>
		);
	}

	return (
		<>
			<Header onBack={backToChat}
				status={
					isLocked
						? { label: 'SUMMARIZING', color: 'text-accent-3', dot: '●', blink: true }
						: { label: 'WAITING', color: 'text-info', dot: '●', blink: false }
				}
			>
				<span className="text-fg-2 text-sm tracking-[0.05em]">Memory</span>
			</Header>

			<div className="flex-1 overflow-y-auto p-5 custom-scrollbar flex flex-col gap-4">
				<p className="text-fg-3 text-sm">
					Long-term memory for this conversation. It's injected into the assistant's prompt as
					background context, so it can recall things even from messages it no longer directly
					sees. This memory can be edited directly, generated automatically, or a combination of
					both.
				</p>

				<div className="flex items-center gap-3">
					<Toggle
						checked={autoSummarizeEnabled}
						onChange={toggleAutoSummarize}
						disabled={isTogglingAutoSummarize || isLocked}
					/>
					<span className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase">
						Auto-summarize every 50 messages
					</span>
				</div>

				{isLocked && (
					<div className="flex items-center justify-between gap-4">
						<p className="text-accent-3 text-sm">
							A summary is being generated in the background. Editing and new summary requests are
							disabled until it finishes — this page will update on its own.
						</p>
						<button
							onClick={forceUnlock}
							disabled={isUnlocking}
							className="text-danger hover:opacity-80 transition-opacity text-xs underline shrink-0 cursor-pointer"
						>
							{isUnlocking ? 'CLEARING...' : 'Stuck? Force unlock'}
						</button>
					</div>
				)}

				<textarea
					value={memory}
					onChange={(e) => setMemory(e.target.value)}
					disabled={isLocked}
					placeholder="No long-term memory yet. Chat a while, or generate one below."
					className="w-full flex-1 min-h-[50vh] bg-transparent border border-line-1 rounded p-4 text-fg-1 text-sm font-mono resize-none focus:outline-none focus:border-accent disabled:opacity-50"
				/>

				<p className="text-fg-3 text-xs">
					<strong>Since last update</strong> only processes messages added since memory was last
					refreshed. <strong>As far as possible</strong> reaches back through the last 200 messages
					regardless of what's already covered, and adds to what's here — clear or edit the text
					above first if you want a clean rewrite instead of an addition. Summarizing runs in the
					background and can take a moment to appear — reopen this page later to see the result.
				</p>

				<div className="flex gap-4 justify-end shrink-0">
					<button
						onClick={summarizeSinceLast}
						disabled={isSummarizing || isLocked || pendingCount === 0}
						className="button-primary"
					>
						{isSummarizing ? 'SUMMARIZING...' : `SINCE LAST UPDATE (${pendingCount})`}
					</button>
					<button
						onClick={summarizeAsFarAsPossible}
						disabled={isSummarizing || isLocked}
						className="button-primary"
					>
						{isSummarizing ? 'SUMMARIZING...' : 'AS FAR AS POSSIBLE'}
					</button>
					<button
						onClick={save}
						disabled={isSaving || isLocked}
						className="button-primary"
					>
						{isSaving ? 'SAVING...' : 'SAVE'}
					</button>
				</div>

				<div className="space-y-2 pt-4 border-t border-line-1">
					<span className="text-fg-3 text-[0.7rem] tracking-[0.15em] uppercase block">
						Summarization Prompt
					</span>
					<AssistantMemoryPromptEditor assistantId={assistantId} addToast={addToast} />
				</div>
			</div>
		</>
	);
}
