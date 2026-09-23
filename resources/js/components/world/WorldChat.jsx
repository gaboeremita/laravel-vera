import { useEffect, useRef, useState } from 'react';
import { Mic, MicOff } from 'lucide-react';
import { route } from 'ziggy-js';
import { api } from '../../utils/api.js';
import { useEmotions } from '../../hooks/useEmotions.js';
import { useConversationChat } from '../../hooks/useConversationChat.js';
import { useVoiceMode } from '../../hooks/useVoiceMode.js';
import { stripForSpeech } from '../../utils/parsers.js';
import { isTypingTarget } from './keyboardFocus.js';
import { useTheme } from '../../contexts/ThemeContext.jsx';
import ChatMessage from '../ChatMessage.jsx';

export default function WorldChat({ world, resident, onClose, addToast, onPoseTrigger, worldSessionId, getPositions, getResidentPosture, onVoiceAudio, onAction }) {
	const [conversationId, setConversationId] = useState(null);
	const [input, setInput] = useState('');
	const [isTranscribing, setIsTranscribing] = useState(false);
	const [isResidentSpeaking, setIsResidentSpeaking] = useState(false);
	const scrollRef = useRef(null);
	const inputRef = useRef(null);
	const speakingTimeoutRef = useRef(null);
	const { portraitType, fetchEmotions } = useEmotions();
	const poseNames = [...new Set((resident.assistant.poses ?? []).map((pose) => pose.name))];
	const { theme, setTheme } = useTheme();

	useEffect(() => {
		const worldTheme = world.settings?.theme;
		if (!worldTheme || worldTheme === theme) return;
		const previousTheme = theme;
		setTheme(worldTheme);
		return () => setTheme(previousTheme);
	}, [world.settings?.theme]);

	useEffect(() => {
		let active = true;

		const resolveConversation = async () => {
			try {
				fetchEmotions(resident.assistant.id);

				const existing = await api.get(route('conversations.index', { assistant: resident.assistant.id, worldSessionId }));
				if (existing.ok) {
					const conversations = await existing.json();
					if (conversations.length > 0) {
						if (active) setConversationId(conversations[0].id);
						return;
					}
				}

				const created = await api.post(route('conversations.store', { assistant: resident.assistant.id }), { worldId: world.id, worldSessionId });
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
	}, [resident.assistant.id, worldSessionId]);

	const speakReply = async (rawText, ttsInstructions) => {
		const text = stripForSpeech(rawText);
		if (!text) return;
		try {
			const payload = { text };
			if (ttsInstructions) payload.instructions = ttsInstructions;
			const response = await api.post(route('voice.synthesize', { assistant: resident.assistant.id }), payload);
			if (!response.ok) {
				const errorData = await response.json().catch(() => ({}));
				throw new Error(errorData.message || 'Synthesis failed');
			}
			const duration = await onVoiceAudio(await response.blob());
			clearTimeout(speakingTimeoutRef.current);
			setIsResidentSpeaking(true);
			speakingTimeoutRef.current = setTimeout(() => setIsResidentSpeaking(false), (duration ?? 0) * 1000);
		} catch (error) {
			addToast(error.message || 'Failed to play voice response', 'error');
		}
	};

	const { messages, isLoading, sendMessage } = useConversationChat({
		assistantId: resident.assistant.id,
		conversationId,
		portraitType,
		poseNames,
		onPoseChange: ({ name, triggerId }) => {
			onPoseTrigger?.({ residentId: resident.id, name, triggerId });
		},
		onLoadError: () => { addToast('Unable to load this conversation', 'error'); onClose(); },
		addToast,
		fetchEmotions,
		onVoiceReply: (text, ttsInstructions) => { void speakReply(text, ttsInstructions); },
		onAction,
		extraParams: worldSessionId && getPositions
			? { worldId: world.id, worldSessionId, get positions() { return getPositions(); }, get residentPosture() { return getResidentPosture(resident.id); } }
			: { worldId: world.id, get residentPosture() { return getResidentPosture(resident.id); } },
	});

	useEffect(() => {
		if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
	}, [messages]);

	const handleSpeechEnd = async (audioBlob) => {
		const formData = new FormData();
		formData.append('audio', audioBlob, 'speech.wav');
		setIsTranscribing(true);
		try {
			const response = await api.postForm(route('voice.transcribe', { assistant: resident.assistant.id }), formData);
			if (!response.ok) {
				const errorData = await response.json().catch(() => ({}));
				throw new Error(errorData.message || 'Transcription failed');
			}
			const { text } = await response.json();
			if (text?.trim() && conversationId) sendMessage(text, { voiceMode: true });
		} catch (error) {
			addToast(error.message || 'Failed to transcribe audio', 'error');
		} finally {
			setIsTranscribing(false);
		}
	};

	const { isListening, isSpeaking, error: voiceError, start: startVoiceMode, stop: stopVoiceMode } = useVoiceMode({ onSpeechEnd: handleSpeechEnd });

	useEffect(() => {
		if (voiceError) addToast(voiceError, 'error');
	}, [voiceError, addToast]);

	useEffect(() => () => {
		stopVoiceMode();
		clearTimeout(speakingTimeoutRef.current);
	}, [stopVoiceMode]);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.key !== 'Enter' || isTypingTarget(event.target)) return;
			event.preventDefault();
			inputRef.current?.focus();
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, []);

	const voiceStatus = !isListening ? null
		: isSpeaking ? 'HEARING YOU'
			: isTranscribing || isLoading ? 'PROCESSING'
				: isResidentSpeaking ? 'SPEAKING'
					: 'LISTENING';

	const handleSend = (event) => {
		event.preventDefault();
		const text = input.trim();
		if (!text || !conversationId || isLoading) return;
		setInput('');
		sendMessage(text);
		inputRef.current?.blur();
	};

	return (
		<div className="flex h-full flex-col border border-line-1 bg-bg-0/90 backdrop-blur-sm">
			<header className="flex items-center justify-between gap-3 border-b border-line-1 px-4 py-3">
				<div className="min-w-0">
					<p className="text-fg-3 text-[0.6rem] tracking-[0.12em]">TALKING WITH</p>
					<p className="flex items-center gap-2 text-accent text-sm tracking-[0.05em]"><span className="h-2 w-2 shrink-0 rounded-full bg-accent" />{resident.assistant.name}</p>
				</div>
				<div className="flex items-center gap-3">
					{voiceStatus && <span className="text-accent text-[0.6rem] tracking-[0.12em]">{voiceStatus}</span>}
					<button type="button" onClick={isListening ? stopVoiceMode : startVoiceMode} title={isListening ? 'Turn voice mode off' : 'Turn voice mode on'} className={`cursor-pointer ${isListening ? 'text-accent' : 'text-fg-3 hover:text-fg-1'}`}>
						{isListening ? <Mic size={16} /> : <MicOff size={16} />}
					</button>
					<button type="button" onClick={onClose} className="text-fg-3 text-xs hover:text-fg-1 cursor-pointer">END (C)</button>
				</div>
			</header>
			<div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto p-4 custom-scrollbar">
				{messages.map((msg) => (
					<ChatMessage key={msg.id} msg={msg} assistantName={resident.assistant.name} />
				))}
			</div>
			<form onSubmit={handleSend} className="flex gap-2 border-t border-line-1 p-3">
				<input
					ref={inputRef}
					value={input}
					onKeyDown={(event) => { if (event.key === 'Escape') event.currentTarget.blur(); }}
					onChange={(event) => setInput(event.target.value)}
					placeholder="Enter to type, Esc to keep walking"
					disabled={!conversationId || isLoading}
					className="min-w-0 flex-1 bg-bg-1 px-3 py-2 text-sm text-fg-1 outline-none"
				/>
				<button disabled={!conversationId || isLoading || !input.trim()} className="button-primary text-[0.7rem]">
					{isLoading ? '...' : 'SEND'}
				</button>
			</form>
		</div>
	);
}
