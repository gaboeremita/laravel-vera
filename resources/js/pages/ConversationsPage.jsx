import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import ConversationList from '../components/ConversationList.jsx';
import Header from '../components/Header.jsx';
import { api } from '../utils/api.js';

export default function ConversationsPage() {
	const { conversations, setConversations, addToast, assistantId} = useOutletContext();
	const navigate = useNavigate();

	const handleSelect = (id) => {
		navigate(`/assistants/${assistantId}/conversations/${id}`);
	};

	const handleNew = async () => {
		try {
			const res = await api.post(route('conversations.store', { assistant: assistantId }));
			const data = await res.json();
			setConversations((prev) => [data, ...prev]);
			navigate(`/assistants/${assistantId}/conversations/${data.id}`);
		} catch {
			addToast('Failed to create conversation', 'error');
		}
	};

	const handleDelete = async (id) => {
		setConversations((prev) => prev.filter((c) => c.id !== id));
	};

	const handleRename = async (id, title) => {
		try {
			await api.patch(route('conversations.update', { assistant: assistantId, id }), { title });
			setConversations((prev) =>
				prev.map((c) => (c.id === id ? { ...c, title } : c))
			);
		} catch (error) {
			console.error('Failed to rename conversation:', error);
		}
	};

	return (
		<>
			<Header settingsPath={`/assistants/${assistantId}/settings`}
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`CONVERSATIONS: ${conversations.length}`}
				actions={
					<div className="flex gap-2">
						<button
							onClick={() => navigate('/assistants')}
							className="button-primary"
						>
							← ASSISTANTS
						</button>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/prompt`)}
							className="button-primary"
						>
							PROMPT
						</button>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/providers`)}
							className="button-primary"
						>
							PROVIDERS
						</button>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/archive`)}
							className="button-primary"
						>
							ARCHIVE
						</button>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/voice`)}
							className="button-primary"
						>
							VOICE
						</button>
					</div>
				}
			>
				<span className="text-fg-2 text-lg tracking-[0.05em]">
					Conversations
				</span>
			</Header>

			<div className="flex-1 overflow-y-auto">
				<ConversationList
					assistantId={assistantId}
					conversations={conversations}
					onSelect={handleSelect}
					onNew={handleNew}
					onDelete={handleDelete}
					onRename={handleRename}
				/>
			</div>
		</>
	);
}