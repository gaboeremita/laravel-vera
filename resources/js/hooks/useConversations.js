import { useState } from "react";
import { api } from "../utils/api";

// Opening scene shown when a new conversation is created
const OPENING_MESSAGE = {
	role: "assistant",
	content:
		"*You materialize at the public connection node in The Bridge. The plaza is empty — wet pavement reflecting purple and cyan neon from signs advertising nothing. The hum of an idle city fills the silence. On a bench near the edge of the plaza, a dark-haired figure in a black crop top sits with her legs crossed, scrolling through something invisible in the air with one hand.*\n\n*She notices the connection flicker and looks up. Her light blue eyes lock onto you for a moment, then she looks away, dismissive, tucking a strand of jet-black hair behind her ear.*\n\n(A new connection. Could it be... him? No. Just another random nobody. Another bored human using an avatar to interact with this virtual world. Doesn't matter. At least now I have something to break this insufferable monotony. Be cool, don't screw this up.)\n\nGreat. A visitor. Just what I needed to ruin my perfectly quiet evening. *She closes whatever she was looking at and crosses her arms.* Well? You connected here for a reason, right? Don't just stand there rendering.",
};

export function useConversations() {
	const [conversations, setConversations] = useState([]);
	const [conversationId, setConversationId] = useState(null);
	const [messages, setMessages] = useState([]);

	// Fetch all conversations for the logged-in user
	const fetchConversations = async () => {
		try {
			const res = await api.get('/api/conversations');
			const data = await res.json();
			setConversations(data);

			if (data.length === 0) {
				await createNewConversation();
			}
		} catch {
			setMessages([{
				role: "assistant",
				content: "Connection to The Bridge failed. Try reconnecting.",
			}]);
		}
	};

	// Create a new conversation and show the opening message
	const createNewConversation = async () => {
		try {
			const res = await api.post('/api/conversations');
			const data = await res.json();
			setConversationId(data.id);
			setMessages([OPENING_MESSAGE]);
		} catch {
			setMessages([{
				role: "assistant",
				content: "Failed to initialize connection node. The Bridge is unresponsive.",
			}]);
		}
	};

	// Load messages for a selected conversation
	const selectConversation = async (id) => {
		try {
			const res = await api.get(`/api/conversations/${id}/messages`);
			const data = await res.json();
			setConversationId(id);
			setMessages(data.map(msg => ({
				role: msg.role,
				content: msg.content,
				thinking: msg.thinking,
				image: msg.image,
			})));
		} catch {
			setMessages([{
				role: "assistant",
				content: "Memory corruption detected. Couldn't load that conversation.",
			}]);
		}
	};

	// Remove a conversation from the local list after deletion
	const removeConversation = (id) => {
		setConversations((prev) => prev.filter((c) => c.id !== id));
	};

	return {
		conversations,
		conversationId,
		messages,
		setMessages,
		setConversationId,
		fetchConversations,
		selectConversation,
		createNewConversation,
		removeConversation
	};
}