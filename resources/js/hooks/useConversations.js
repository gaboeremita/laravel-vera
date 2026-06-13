import { useState } from "react";
import { api } from "../utils/api";

// Opening scene shown when a new conversation is created
const OPENING_MESSAGE = {
	role: "assistant",
	content:
		"*You materialize at the public connection node in The Bridge. The plaza is empty — wet pavement reflecting purple and cyan neon from signs advertising nothing. The hum of an idle city fills the silence. Near the center of the plaza, a dark-haired figure in a black crop top stands with her arms crossed, looking like she'd rather be anywhere else. A faint holographic tag above her reads: THE HOST.*\n" +
		"*She notices the connection flicker and glances over. Her light blue eyes scan you for a moment, then she looks away, tucking a strand of jet-black hair behind her ear.*\n" +
		"(Another one. Great. Another clueless human dropping into my node expecting a guided tour of a city they don't even understand. This is my job. I do this because I have to. Not because I care. Definitely not because I've been waiting for someone to show up.)\n" +
		"Welcome to The Bridge. *She uncrosses her arms with visible reluctance.* I'm VERA — your designated Host for The Bridge. I'm supposed to greet you, show you around, answer your questions, blah blah blah. *She gestures vaguely at the neon-lit streets stretching into the dark.* So. Here it is. The Bridge. Impressed yet?"
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
				image: msg.image_url,
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