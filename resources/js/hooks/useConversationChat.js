import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { parseEmotionFromResponse, parsePoseFromResponse } from '../utils/parsers.js';

const CREATOR_MODE_TRIGGER = '[creator mode: "tsuru tuneado"]';

function mapMessage(msg, portraitType, poseNames, emotionNames) {
	if (msg.role !== 'assistant') {
		return { id: msg.id, role: msg.role, content: msg.content, thinking: msg.thinking, image: msg.image_url };
	}

	if (portraitType === 'avatar3d') {
		const { text } = parsePoseFromResponse(msg.content, poseNames);
		return { id: msg.id, role: msg.role, content: text, thinking: msg.thinking, image: msg.image_url };
	}

	const { emotion, text } = parseEmotionFromResponse(msg.content, emotionNames);
	return { id: msg.id, role: msg.role, content: text, thinking: msg.thinking, image: msg.image_url, emotion };
}

/**
 * Shared conversation-loading and send-message logic behind both a normal
 * 1:1 chat (ChatPage) and in-world resident chat (WorldChat). Owns no input
 * field state — callers pass the text to send explicitly — so a draft/voice
 * transcription UI on top is entirely the caller's concern.
 */
export function useConversationChat({
	assistantId,
	conversationId,
	portraitType,
	poseNames = [],
	emotionNames = [],
	onPoseChange,
	onEmotionChange,
	onVoiceReply,
	onLoadError,
	addToast,
	fetchEmotions,
	unlocked,
	extraParams = {},
}) {
	const [messages, setMessages] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [hasError, setHasError] = useState(false);
	const [hasMore, setHasMore] = useState(false);
	const [isLoadingMore, setIsLoadingMore] = useState(false);

	useEffect(() => {
		if (!assistantId || !conversationId) return;
		let cancelled = false;

		const loadMessages = async () => {
			try {
				const res = await api.get(route('conversations.show', { assistant: assistantId, id: conversationId }));
				if (!res.ok) throw new Error('Conversation unavailable');

				const data = await res.json();
				let lastEmotion = null;

				// Poses are one-off triggers, not an ongoing state, so history
				// isn't replayed into the current pose the way the last emotion
				// is — reopening a conversation just starts the avatar idle.
				const mapped = data.messages.map((msg) => {
					const result = mapMessage(msg, portraitType, poseNames, emotionNames);
					if (msg.role === 'assistant' && portraitType !== 'avatar3d') lastEmotion = result.emotion;
					return result;
				});

				if (cancelled) return;
				setMessages(mapped);
				setHasMore(data.has_more);
				if (lastEmotion) onEmotionChange?.(lastEmotion);
			} catch (error) {
				if (!cancelled) onLoadError?.(error);
			}
		};

		loadMessages();
	}, [assistantId, conversationId, portraitType]);

	const loadOlderMessages = useCallback(async (scrollElement) => {
		if (isLoadingMore || !hasMore || messages.length === 0) return;

		const oldestId = messages[0].id;
		if (!oldestId) return;

		setIsLoadingMore(true);
		const previousScrollHeight = scrollElement?.scrollHeight ?? 0;

		try {
			const url = route('conversations.show', { assistant: assistantId, id: conversationId }) + `?before=${oldestId}`;
			const res = await api.get(url);
			if (!res.ok) return;

			const data = await res.json();
			const mapped = data.messages.map((msg) => mapMessage(msg, portraitType, poseNames, emotionNames));

			setMessages((prev) => [...mapped, ...prev]);
			setHasMore(data.has_more);

			requestAnimationFrame(() => {
				if (scrollElement) scrollElement.scrollTop = scrollElement.scrollHeight - previousScrollHeight;
			});
		} catch {
			addToast?.('Failed to load older messages', 'error');
		} finally {
			setIsLoadingMore(false);
		}
	}, [isLoadingMore, hasMore, messages, assistantId, conversationId, portraitType, poseNames, emotionNames, addToast]);

	const sendMessage = useCallback(async (text, { voiceMode = false, image = null } = {}) => {
		const trimmed = (text || '').trim();
		if ((!trimmed && !image) || isLoading) return;

		const isImageGen = trimmed.toLowerCase().startsWith('/create-image ');

		const userMsg = { id: `temp-${Date.now()}`, role: 'user', content: trimmed, image: image || null };
		const updatedMessages = [...messages, userMsg];
		setMessages([...updatedMessages, { role: 'assistant', content: '', loading: true, generatingImage: isImageGen }]);
		setIsLoading(true);

		const apiMessages = updatedMessages.map((m) => {
			const msg = { role: m.role, content: m.content || '' };
			if (m.image && m.image.startsWith('data:')) {
				msg.images = [m.image.replace(/^data:image\/\w+;base64,/, '')];
			}
			return msg;
		});

		const maxRetries = 3;
		let lastError = null;

		for (let attempt = 1; attempt <= maxRetries; attempt++) {
			try {
				if (trimmed.toLowerCase().includes(CREATOR_MODE_TRIGGER.toLowerCase())) {
					fetchEmotions?.(assistantId);
				}

				const response = await api.post(route('conversations.sendMessage', { assistant: assistantId, id: conversationId }), {
					messages: apiMessages,
					...(voiceMode ? { voice_mode: true } : {}),
					...extraParams,
				});

				if (!response.ok) {
					const errorData = await response.json().catch(() => ({}));
					throw new Error(errorData.message || 'Request failed');
				}

				const data = await response.json();

				if (data.image_url) {
					if (data.intimate !== unlocked) fetchEmotions?.(assistantId);

					if (portraitType === 'avatar3d') {
						if (data.pose) onPoseChange?.({ name: data.pose, triggerId: Date.now() });
					} else {
						onEmotionChange?.(data.emotion || (data.intimate ? 'seduced' : 'default'));
					}

					setHasError(false);
					setMessages([...updatedMessages, { id: `temp-${Date.now()}-reply`, role: 'assistant', content: data.content || '', thinking: data.thinking || null, image: data.image_url }]);
					setIsLoading(false);
					return;
				}

				const rawReply = data.content || '[default]\n...signal lost. Try again.';
				const thinking = data.thinking || null;

				// Poses and emotions are mutually exclusive by portrait type (a 3D
				// avatar has no emotion tags to disambiguate against), so only the
				// applicable parser ever runs on a given reply. A background-change
				// reply already has its tag stripped and its pose parsed server-side
				// (data.pose is present, unlike a normal reply) — use that directly
				// instead of re-parsing content that no longer has a tag to find.
				let emotion = 'default';
				let intimate = false;
				let pose = data.pose !== undefined ? data.pose : null;
				let cleanText = rawReply;

				if (data.pose === undefined) {
					if (portraitType === 'avatar3d') {
						({ pose, text: cleanText } = parsePoseFromResponse(rawReply, poseNames));
					} else {
						({ emotion, intimate, text: cleanText } = parseEmotionFromResponse(rawReply, emotionNames));
					}
				}

				const ttsInstructions = data.tts_instructions ?? null;

				if (intimate !== unlocked) fetchEmotions?.(assistantId);

				const generatedImageMessages = (data.tool_calls || [])
					.filter((call) => call.result?.image_url)
					.map((call, index) => ({ id: `temp-${Date.now()}-image-${index}`, role: 'assistant', content: '', image: call.result.image_url }));

				if (portraitType === 'avatar3d') {
					if (pose) onPoseChange?.({ name: pose, triggerId: Date.now() });
				} else {
					onEmotionChange?.(emotion);
				}

				setHasError(false);
				setMessages([...updatedMessages, ...generatedImageMessages, { id: `temp-${Date.now()}-reply`, role: 'assistant', content: cleanText, thinking, ttsInstructions, toolCalls: data.tool_calls || null }]);
				setIsLoading(false);
				if (voiceMode) onVoiceReply?.(cleanText, ttsInstructions);
				return;
			} catch (error) {
				lastError = error;
				setHasError(true);

				const msg = error.message?.toLowerCase() || '';
				const isTimeout = msg.includes('timeout') || msg.includes('execution time') || msg.includes('502') || msg.includes('504');

				if (isTimeout && attempt < maxRetries) {
					addToast?.(`Signal interference. Retrying... (${attempt}/${maxRetries})`, 'error');
					await new Promise((r) => setTimeout(r, 2000));
					continue;
				}
				break;
			}
		}

		addToast?.(lastError?.message || 'Connection to The Bridge failed', 'error');
		setMessages([...updatedMessages]);
		setIsLoading(false);
	}, [messages, isLoading, assistantId, conversationId, portraitType, poseNames, emotionNames, unlocked, extraParams, fetchEmotions, onPoseChange, onEmotionChange, onVoiceReply, addToast]);

	return { messages, setMessages, isLoading, hasError, hasMore, isLoadingMore, sendMessage, loadOlderMessages };
}
