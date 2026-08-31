import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../../utils/api.js';

export default function WorldChat({ world, resident, onClose, addToast }) {
	const [conversationId, setConversationId] = useState(null);
	const [messages, setMessages] = useState([]);
	const [input, setInput] = useState('');
	const [isSending, setIsSending] = useState(false);

	useEffect(() => {
		let active = true;
		const create = async () => {
			try {
				const response = await api.post(route('conversations.store', { assistant: resident.assistant.id }), {});
				if (!response.ok) throw new Error('Unable to start a conversation');
				const conversation = await response.json();
				if (!active) return;
				setConversationId(conversation.id);
				if (resident.assistant.opening_message) setMessages([{ role: 'assistant', content: resident.assistant.opening_message }]);
			} catch (error) { addToast(error.message || 'Unable to start a conversation', 'error'); onClose(); }
		};
		void create();
		return () => { active = false; };
	}, [addToast, onClose, resident.assistant.id, resident.assistant.opening_message]);

	const send = async (event) => {
		event.preventDefault();
		const content = input.trim();
		if (!content || !conversationId || isSending) return;
		const nextMessages = [...messages, { role: 'user', content }];
		setMessages(nextMessages); setInput(''); setIsSending(true);
		try {
			const response = await api.post(route('conversations.sendMessage', { assistant: resident.assistant.id, id: conversationId }), { messages: nextMessages.map(({ role, content: message }) => ({ role, content: message })), world_id: world.id });
			if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || 'Message failed');
			const reply = await response.json();
			setMessages((current) => [...current, { role: 'assistant', content: reply.content }]);
		} catch (error) { addToast(error.message || 'Message failed', 'error'); } finally { setIsSending(false); }
	};

	return <aside className="absolute right-5 top-5 bottom-5 z-20 flex w-[min(28rem,calc(100%-2.5rem))] flex-col border border-line-1 bg-bg-0/95 shadow-2xl backdrop-blur"><header className="flex items-center justify-between border-b border-line-1 px-4 py-3"><div><p className="text-accent text-sm tracking-[0.05em]">{resident.assistant.name}</p><p className="text-fg-3 text-[0.65rem] tracking-[0.1em]">IN {world.name.toUpperCase()}</p></div><button type="button" onClick={onClose} className="text-fg-3 text-xs hover:text-fg-1">CLOSE</button></header><div className="flex-1 space-y-3 overflow-y-auto p-4 custom-scrollbar">{messages.map((message, index) => <p key={`${message.role}-${index}`} className={message.role === 'user' ? 'ml-8 text-right text-fg-1 text-sm' : 'mr-8 text-fg-2 text-sm'}>{message.content}</p>)}</div><form onSubmit={send} className="flex gap-2 border-t border-line-1 p-3"><input value={input} onChange={(event) => setInput(event.target.value)} placeholder="Write a message..." className="min-w-0 flex-1 bg-bg-1 px-3 py-2 text-sm text-fg-1 outline-none" autoFocus /><button disabled={isSending || !conversationId} className="button-primary text-[0.7rem]">{isSending ? '...' : 'SEND'}</button></form></aside>;
}
