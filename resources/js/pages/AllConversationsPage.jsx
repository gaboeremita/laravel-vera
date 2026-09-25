import { useEffect, useState } from 'react';
import { useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { route } from 'ziggy-js';
import Header from '../components/Header.jsx';
import { api } from '../utils/api.js';

const partyNames = (conversation) => [conversation.owner?.name, conversation.counterpart?.name].filter(Boolean).join(' & ');

/**
 * Every conversation the user can see: their own chats and the ones their
 * assistants have with each other, read-only.
 */
export default function AllConversationsPage() {
	const { conversationId } = useParams();
	const navigate = useNavigate();
	const { addToast } = useOutletContext();
	const [conversations, setConversations] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [selected, setSelected] = useState(null);

	useEffect(() => {
		let cancelled = false;
		void (async () => {
			try {
				const response = await api.get(route('conversation-browser.index'));
				if (!response.ok) throw new Error(`HTTP ${response.status}`);
				const { data } = await response.json();
				if (!cancelled) setConversations(data);
			} catch (error) {
				addToast(`Could not load conversations (${error.message})`, 'error');
			} finally {
				if (!cancelled) setIsLoading(false);
			}
		})();
		return () => { cancelled = true; };
	}, [addToast]);

	useEffect(() => {
		if (!conversationId) return undefined;
		let cancelled = false;
		void (async () => {
			try {
				const response = await api.get(route('conversation-browser.show', { conversation: conversationId }));
				if (!response.ok) throw new Error(`HTTP ${response.status}`);
				const { data } = await response.json();
				if (!cancelled) setSelected(data);
			} catch (error) {
				addToast(`Could not load the conversation (${error.message})`, 'error');
			}
		})();
		return () => { cancelled = true; };
	}, [conversationId, addToast]);

	const shown = conversationId && selected && String(selected.id) === String(conversationId) ? selected : null;
	const userChatAssistant = shown && (shown.owner?.type === 'user' ? shown.counterpart : shown.counterpart?.type === 'user' ? shown.owner : null);

	return (
		<>
			<Header hideSettings onBack={() => navigate('/')} status={{ label: isLoading ? 'LOADING' : 'WAITING', color: isLoading ? 'text-warning' : 'text-info', dot: '●', blink: isLoading }} counter={!isLoading ? `CONVERSATIONS: ${conversations.length}` : null}>
				<span className="text-fg-2 text-lg tracking-[0.05em]">Conversations</span>
			</Header>
			<div className="flex flex-1 min-h-0">
				<div className="w-80 shrink-0 overflow-y-auto border-r border-line-1 custom-scrollbar">
					{conversations.map((conversation) => (
						<button
							key={conversation.id}
							type="button"
							onClick={() => navigate(`/conversations/${conversation.id}`)}
							className={`block w-full border-b border-line-1 px-4 py-3 text-left transition-colors hover:bg-bg-1 cursor-pointer ${String(conversation.id) === String(conversationId) ? 'bg-bg-1' : ''}`}
						>
							<p className="truncate text-accent text-sm tracking-[0.05em]">{partyNames(conversation)}</p>
							<p className="mt-1 truncate text-fg-3 text-[0.65rem] tracking-[0.1em]">
								{[conversation.world?.name, conversation.status === 'paused' ? 'STOPPED' : null, conversation.title].filter(Boolean).join(' · ')}
							</p>
						</button>
					))}
				</div>
				<div className="flex flex-1 min-w-0 flex-col">
					{shown ? (
						<>
							<div className="flex items-center justify-between gap-3 border-b border-line-1 px-5 py-3">
								<p className="truncate text-fg-1 text-sm">{partyNames(shown)}</p>
								{userChatAssistant && (
									<button type="button" onClick={() => navigate(`/assistants/${userChatAssistant.id}/conversations/${shown.id}`)} className="text-fg-3 text-[0.7rem] tracking-[0.1em] hover:text-fg-1 cursor-pointer">OPEN CHAT</button>
								)}
							</div>
							<div className="flex-1 space-y-3 overflow-y-auto p-5 custom-scrollbar">
								{shown.messages.map((message) => (
									<div key={message.id}>
										<p className="text-accent text-[0.65rem] tracking-[0.1em]">{(message.speaker?.name ?? 'Someone').toUpperCase()}&gt;</p>
										<p className="whitespace-pre-wrap text-fg-1 text-sm">{message.content}</p>
									</div>
								))}
							</div>
						</>
					) : (
						<div className="flex flex-1 items-center justify-center text-fg-3 text-sm tracking-[0.1em]">{conversationId ? 'LOADING…' : 'PICK A CONVERSATION'}</div>
					)}
				</div>
			</div>
		</>
	);
}
