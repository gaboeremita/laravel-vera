import { useNavigate, useOutletContext } from 'react-router-dom';
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
			const res = await api.post('/api/conversations');
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
			await api.patch(`/api/conversations/${id}`, { title });
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
				status={{ label: 'WAITING', color: 'text-blue-400', dot: '●', blink: false }}
				counter={`CONVERSATIONS: ${conversations.length}`}
			>
				<span className="text-[#7070a0] text-sm tracking-[0.05em]">
					// Conversations
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