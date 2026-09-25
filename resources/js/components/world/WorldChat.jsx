import { useEffect, useRef, useState } from 'react';
import { Mic, MicOff } from 'lucide-react';
import { route } from 'ziggy-js';
import { api } from '../../utils/api.js';
import { useEmotions } from '../../hooks/useEmotions.js';
import { useConversationChat } from '../../hooks/useConversationChat.js';
import { useVoiceMode } from '../../hooks/useVoiceMode.js';
import { spokenWords, stripForSpeech } from '../../utils/parsers.js';
import { isTypingTarget } from './keyboardFocus.js';
import { joinLines } from './activityLines.js';
import { useTheme } from '../../contexts/ThemeContext.jsx';
import ChatMessage from '../ChatMessage.jsx';

export default function WorldChat({ world, resident, onClose, addToast, onPoseTrigger, worldSessionId, getPositions, getResidentPosture, getResidentState, getUserState, getOccupiedSpots, getStackedSpots, onVoiceAudio, onSilentReply, onAction, actionSender: actionSenderRef }) {
	const [conversationId, setConversationId] = useState(null);
	const [input, setInput] = useState('');
	const [pendingImage, setPendingImage] = useState(null);
	const fileInputRef = useRef(null);
	const [isTranscribing, setIsTranscribing] = useState(false);
	const [isResidentSpeaking, setIsResidentSpeaking] = useState(false);
	const scrollRef = useRef(null);
	const inputRef = useRef(null);
	const speakingTimeoutRef = useRef(null);
	const queuedLines = useRef([]);
	const [queueVersion, setQueueVersion] = useState(0);
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
			? { worldId: world.id, worldSessionId, get positions() { return getPositions(); }, get residentPosture() { return getResidentPosture(resident.id); }, get residentState() { return getResidentState?.(resident.id) ?? null; }, get userState() { return getUserState?.() ?? null; }, get occupiedSpots() { return getOccupiedSpots?.(resident.id) ?? []; }, get stackedSpots() { return getStackedSpots?.() ?? []; } }
			: { worldId: world.id, get residentPosture() { return getResidentPosture(resident.id); }, get residentState() { return getResidentState?.(resident.id) ?? null; }, get userState() { return getUserState?.() ?? null; }, get occupiedSpots() { return getOccupiedSpots?.(resident.id) ?? []; }, get stackedSpots() { return getStackedSpots?.() ?? []; } },
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
			const words = spokenWords(text);
			if (words && conversationId) sendMessage(words, { voiceMode: true });
		} catch (error) {
			addToast(error.message || 'Failed to transcribe audio', 'error');
		} finally {
			setIsTranscribing(false);
		}
	};

	const { isListening, isSpeaking, error: voiceError, start: startVoiceMode, stop: stopVoiceMode } = useVoiceMode({ onSpeechEnd: handleSpeechEnd });

	// A reply that arrived while voice mode was off is still her speaking;
	// history loaded when the chat opens has server ids and is skipped.
	const lastReplyIdRef = useRef(null);
	useEffect(() => {
		const last = messages[messages.length - 1];
		if (!last || last.role !== 'assistant' || last.loading || !String(last.id).startsWith('temp-') || last.id === lastReplyIdRef.current) return;
		lastReplyIdRef.current = last.id;
		if (!isListening) onSilentReply?.(last.content);
	}, [messages, isListening, onSilentReply]);

	useEffect(() => {
		if (voiceError) addToast(voiceError, 'error');
	}, [voiceError, addToast]);

	useEffect(() => () => {
		stopVoiceMode();
		clearTimeout(speakingTimeoutRef.current);
	}, [stopVoiceMode]);

	useEffect(() => {
		if (!actionSenderRef) return undefined;
		actionSenderRef.current = (line) => {
			queuedLines.current.push(line);
			setQueueVersion((version) => version + 1);
		};
		return () => { actionSenderRef.current = null; };
	}, [actionSenderRef]);

	useEffect(() => {
		if (queuedLines.current.length === 0 || !conversationId || isLoading) return;
		void sendMessage(joinLines(queuedLines.current.splice(0)), { voiceMode: isListening });
	}, [queueVersion, conversationId, isLoading, sendMessage, isListening]);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.key !== 'Enter' || event.defaultPrevented || isTypingTarget(event.target)) return;
			event.preventDefault();
			inputRef.current?.focus();
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, []);

	useEffect(() => {
		const keyDown = (event) => {
			if (event.code !== 'KeyV' || event.repeat || isTypingTarget(event.target)) return;
			event.preventDefault();
			if (isListening) stopVoiceMode();
			else startVoiceMode();
		};
		window.addEventListener('keydown', keyDown);
		return () => window.removeEventListener('keydown', keyDown);
	}, [isListening, startVoiceMode, stopVoiceMode]);

	const voiceStatus = !isListening ? null
		: isSpeaking ? 'HEARING YOU'
			: isTranscribing || isLoading ? 'PROCESSING'
				: isResidentSpeaking ? 'SPEAKING'
					: 'LISTENING';

	const handleSend = (event) => {
		event.preventDefault();
		const text = input.trim();
		if ((!text && !pendingImage) || !conversationId || isLoading) return;
		const image = pendingImage;
		setInput('');
		setPendingImage(null);
		sendMessage(text, { image });
		inputRef.current?.blur();
	};

	const handleImageSelect = (event) => {
		const file = event.target.files?.[0];
		if (!file) return;
		const reader = new FileReader();
		reader.onload = () => setPendingImage(reader.result);
		reader.readAsDataURL(file);
		event.target.value = '';
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
					<button type="button" onClick={isListening ? stopVoiceMode : startVoiceMode} title={isListening ? 'Turn voice mode off (V)' : 'Turn voice mode on (V)'} className={`cursor-pointer ${isListening ? 'text-accent' : 'text-fg-3 hover:text-fg-1'}`}>
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
			{pendingImage && (
				<div className="flex items-center gap-2 border-t border-line-1 px-3 py-2">
					<img src={pendingImage} alt="Pending upload" className="h-16 w-16 object-cover rounded border border-line-1" />
					<button type="button" onClick={() => setPendingImage(null)} className="text-danger text-xs hover:text-danger cursor-pointer">✕</button>
				</div>
			)}
			<form onSubmit={handleSend} className="flex items-center gap-2 border-t border-line-1 p-3">
				<input ref={fileInputRef} type="file" accept="image/*" onChange={handleImageSelect} className="hidden" />
				<button type="button" onClick={() => fileInputRef.current?.click()} disabled={!conversationId || isLoading} title="Attach an image" className="text-fg-3 hover:text-accent transition-colors shrink-0 cursor-pointer disabled:opacity-50 disabled:cursor-default">
					📎
				</button>
				<input
					ref={inputRef}
					value={input}
					onKeyDown={(event) => { if (event.key === 'Escape') event.currentTarget.blur(); }}
					onChange={(event) => setInput(event.target.value)}
					placeholder="Enter to type, Esc to keep walking"
					disabled={!conversationId || isLoading}
					className="min-w-0 flex-1 bg-bg-1 px-3 py-2 text-sm text-fg-1 outline-none"
				/>
				<button disabled={!conversationId || isLoading || (!input.trim() && !pendingImage)} className="button-primary text-[0.7rem]">
					{isLoading ? '...' : 'SEND'}
				</button>
			</form>
		</div>
	);
}
