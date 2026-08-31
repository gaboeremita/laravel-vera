import { useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../../utils/api.js';
import { useEmotions } from '../../hooks/useEmotions.js';
import { useConversationChat } from '../../hooks/useConversationChat.js';
import ChatMessage from '../ChatMessage.jsx';

export default function WorldChat({ world, resident, onClose, addToast, onPoseTrigger }) {
	const [conversationId, setConversationId] = useState(null);
	const [input, setInput] = useState('');
	const scrollRef = useRef(null);
	const { poses, portraitType, fetchEmotions } = useEmotions();

	useEffect(() => {
		let active = true;

		const resolveConversation = async () => {
			try {
				fetchEmotions(resident.assistant.id);

				const existing = await api.get(route('conversations.index', { assistant: resident.assistant.id }));
				if (existing.ok) {
					const conversations = await existing.json();
					if (conversations.length > 0) {
						if (active) setConversationId(conversations[0].id);
						return;
					}
				}

				const created = await api.post(route('conversations.store', { assistant: resident.assistant.id }), { worldId: world.id });
				if (!created.ok) throw new Error('Unable to start a conversation');
				const conversation = await created.json();
				if (active) setConversationId(conversation.id);
			} catch (error) {
				addToast(error.message || 'Unable to start a conversation', 'error');
				onClose();
			}
		};

		void resolveConversation();
		return () => { active = false; };
	}, [resident.assistant.id]);

	const { messages, isLoading, sendMessage } = useConversationChat({
		assistantId: resident.assistant.id,
		conversationId,
		portraitType,
		poseNames: poses.map((p) => p.name),
		onPoseChange: ({ name, triggerId }) => {
			const pose = poses.find((p) => p.name === name);
			onPoseTrigger?.({ residentId: resident.id, animationUrl: pose?.animation_url ?? null, blendshapes: pose?.vrm_blendshapes ?? [], triggerId });
		},
		onLoadError: () => { addToast('Unable to load this conversation', 'error'); onClose(); },
		addToast,
		fetchEmotions,
		extraParams: { worldId: world.id },
	});

	useEffect(() => {
		if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
	}, [messages]);

	const handleSend = (event) => {
		event.preventDefault();
		const text = input.trim();
		if (!text || !conversationId || isLoading) return;
		setInput('');
		sendMessage(text);
	};

	return (
		<div className="flex h-full flex-col border-r border-line-1 bg-bg-0">
			<header className="flex items-center justify-between border-b border-line-1 px-4 py-3">
				<div>
					<p className="text-accent text-sm tracking-[0.05em]">{resident.assistant.name}</p>
					<p className="text-fg-3 text-[0.65rem] tracking-[0.1em]">IN {world.name.toUpperCase()}</p>
				</div>
				<button type="button" onClick={onClose} className="text-fg-3 text-xs hover:text-fg-1 cursor-pointer">CLOSE</button>
			</header>
			<div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto p-4 custom-scrollbar">
				{messages.map((msg) => (
					<ChatMessage key={msg.id} msg={msg} assistantName={resident.assistant.name} />
				))}
			</div>
			<form onSubmit={handleSend} className="flex gap-2 border-t border-line-1 p-3">
				<input
					value={input}
					onChange={(event) => setInput(event.target.value)}
					placeholder="Write a message..."
					disabled={!conversationId || isLoading}
					className="min-w-0 flex-1 bg-bg-1 px-3 py-2 text-sm text-fg-1 outline-none"
					autoFocus
				/>
				<button disabled={!conversationId || isLoading || !input.trim()} className="button-primary text-[0.7rem]">
					{isLoading ? '...' : 'SEND'}
				</button>
			</form>
		</div>
	);
}
