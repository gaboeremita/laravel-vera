import { useNavigate, useOutletContext } from 'react-router-dom';
import { route } from 'ziggy-js';
import ConversationList from '../components/ConversationList.jsx';
import Header from '../components/Header.jsx';
import { api } from '../utils/api.js';

export default function ConversationsPage() {
	const { conversations, setConversations, addToast } = useOutletContext();
	const navigate = useNavigate();

	const handleSelect = (id) => {
		navigate(`/conversations/${id}`);
	};

	const handleNew = async () => {
		try {
			const res = await api.post(route('conversations.store', { assistant: 1 }));
			const data = await res.json();
			setConversations((prev) => [data, ...prev]);
			navigate(`/conversations/${data.id}`);
		} catch {
			addToast('Failed to create conversation', 'error');
		}
	};

	const handleDelete = async (id) => {
		setConversations((prev) => prev.filter((c) => c.id !== id));
	};

	const handleRename = async (id, title) => {
		try {
			await api.patch(route('conversations.update', { assistant: 1, id }), { title });
			setConversations((prev) =>
				prev.map((c) => (c.id === id ? { ...c, title } : c))
			);
		} catch (error) {
			console.error('Failed to rename conversation:', error);
		}
	};

	return (
		<>
			<Header
				status={{ label: 'WAITING', color: 'text-info', dot: '●', blink: false }}
				counter={`CONVERSATIONS: ${conversations.length}`}
				actions={
					<button
						onClick={() => navigate('/lorebook')}
						className="button-primary"
					>
						LOREBOOK
					</button>
				}
			>
				<span className="text-fg-2 text-lg tracking-[0.05em]">
					Conversations
				</span>
			</Header>

			<div className="flex-1 overflow-y-auto">
				<ConversationList
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