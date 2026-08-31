import { useState, useRef, useEffect } from 'react';
import { useParams, useNavigate, useOutletContext } from 'react-router-dom';
import { Pencil, Mic, MicOff, Volume2, VolumeX, Brain } from 'lucide-react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { stripForSpeech } from '../utils/parsers.js';
import { useVoiceMode } from '../hooks/useVoiceMode.js';
import { useConversationChat } from '../hooks/useConversationChat.js';
import ChatMessage from '../components/ChatMessage.jsx';
import Header from '../components/Header.jsx';
import AgentProgressIndicator from '../components/AgentProgressIndicator.jsx';
import { getAssistantMenuItems } from '../utils/assistantMenu.jsx';

export default function ChatPage() {
	const { id } = useParams();
	const navigate = useNavigate();
	const {
		assistantId,
		assistantName,
		setCurrentEmotion,
		setCurrentPose,
		emotionNames,
		poseNames,
		portraitType,
		addToast,
		fetchEmotions,
		unlocked,
		setConversations,
		conversations,
		setActiveConversationId,
	} = useOutletContext();

	const draftKey = `chatDraft:${assistantId}:${id}`;

	const [input, setInput] = useState(() => localStorage.getItem(draftKey) || '');
	const [pendingImage, setPendingImage] = useState(null);
	const scrollRef = useRef(null);
	const inputRef = useRef(null);
	const fileInputRef = useRef(null);
	const editInputRef = useRef(null);
	const [isEditingTitle, setIsEditingTitle] = useState(false);
	const [editTitleValue, setEditTitleValue] = useState('');
	const [voiceMuted, setVoiceMuted] = useState(false);
	const audioPlayerRef = useRef(null);
	const draftTimeoutRef = useRef(null);

	const conversationTitle = conversations.find((c) => c.id === Number(id))?.title || '';

	// Restore draft when switching conversations
	const [restoredDraftKey, setRestoredDraftKey] = useState(draftKey);
	if (draftKey !== restoredDraftKey) {
		setRestoredDraftKey(draftKey);
		setInput(localStorage.getItem(draftKey) || '');
	}

	useEffect(() => {
		return () => clearTimeout(draftTimeoutRef.current);
	}, [draftKey]);

	const handleInputChange = (e) => {
		const value = e.target.value;
		setInput(value);
		clearTimeout(draftTimeoutRef.current);
		draftTimeoutRef.current = setTimeout(() => {
			if (value.trim()) {
				localStorage.setItem(draftKey, value);
			} else {
				localStorage.removeItem(draftKey);
			}
		}, 500);
	};

	const startEditingTitle = () => {
		setEditTitleValue(conversationTitle);
		setIsEditingTitle(true);
	};

	const cancelEditingTitle = () => {
		setIsEditingTitle(false);
		setEditTitleValue('');
	};

	const saveTitle = async () => {
		const trimmed = editTitleValue.trim();
		if (!trimmed || trimmed === conversationTitle) {
			cancelEditingTitle();
			return;
		}

		try {
			await api.patch(route('conversations.update', { assistant: assistantId, id }), { title: trimmed });
			setConversations((prev) =>
				prev.map((c) => (c.id === Number(id) ? { ...c, title: trimmed } : c))
			);
		} catch {
			addToast('Failed to rename conversation', 'error');
		}

		cancelEditingTitle();
	};

	useEffect(() => {
		if (isEditingTitle && editInputRef.current) {
			editInputRef.current.focus();
			editInputRef.current.select();
		}
	}, [isEditingTitle]);

	const playSynthesizedAudio = async (rawText, ttsInstructions = null) => {
		const text = stripForSpeech(rawText);
		if (!text) return;

		try {
			const payload = { text };
			if (ttsInstructions) payload.instructions = ttsInstructions;
			const response = await api.post(route('voice.synthesize', { assistant: assistantId }), payload);

			if (!response.ok) {
				const errorData = await response.json().catch(() => ({}));
				throw new Error(errorData.message || 'Synthesis failed');
			}

			const audioBlob = await response.blob();
			const url = URL.createObjectURL(audioBlob);

			const previous = audioPlayerRef.current;
			if (previous) {
				previous.pause();
				if (previous.src?.startsWith('blob:')) URL.revokeObjectURL(previous.src);
			}

			const player = new Audio(url);
			audioPlayerRef.current = player;
			player.addEventListener('ended', () => URL.revokeObjectURL(url), { once: true });
			await player.play();
		} catch (error) {
			addToast(error.message || 'Failed to play voice response', 'error');
		}
	};

	const {
		messages,
		isLoading,
		hasError,
		isLoadingMore,
		sendMessage: sendChatMessage,
		loadOlderMessages: loadOlderChatMessages,
	} = useConversationChat({
		assistantId,
		conversationId: id,
		portraitType,
		poseNames,
		emotionNames,
		onPoseChange: setCurrentPose,
		onEmotionChange: setCurrentEmotion,
		onVoiceReply: (text, ttsInstructions) => { if (!voiceMuted) playSynthesizedAudio(text, ttsInstructions); },
		onLoadError: () => navigate(`/assistants/${assistantId}/conversations`, { replace: true }),
		addToast,
		fetchEmotions,
		unlocked,
	});

	useEffect(() => {
		setActiveConversationId(Number(id));
		return () => setActiveConversationId(null);
	}, [id]);

	useEffect(() => {
		if (scrollRef.current) {
			scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
		}
	}, [messages]);

	useEffect(() => {
		if (inputRef.current) inputRef.current.focus();
	}, []);

	useEffect(() => {
		const scrollEl = scrollRef.current;
		if (!scrollEl) return;

		const handleScroll = () => {
			if (scrollEl.scrollTop < 100) {
				loadOlderChatMessages(scrollEl);
			}
		};

		scrollEl.addEventListener('scroll', handleScroll);
		return () => scrollEl.removeEventListener('scroll', handleScroll);
	}, [loadOlderChatMessages]);

	const sendMessage = (overrideText, options = {}) => {
		const usingOverride = overrideText !== undefined;
		const text = (usingOverride ? overrideText : input).trim();
		if ((!text && !pendingImage) || isLoading) return;

		const image = pendingImage;
		if (!usingOverride) setInput('');
		setPendingImage(null);
		clearTimeout(draftTimeoutRef.current);
		localStorage.removeItem(draftKey);

		sendChatMessage(text, { voiceMode: options.voiceMode, image });
	};

	const handleSpeechEnd = async (audioBlob) => {
		const formData = new FormData();
		formData.append('audio', audioBlob, 'speech.wav');

		try {
			const response = await api.postForm(
				route('voice.transcribe', { assistant: assistantId }),
				formData
			);

			if (!response.ok) {
				const errorData = await response.json().catch(() => ({}));
				throw new Error(errorData.message || 'Transcription failed');
			}

			const { text } = await response.json();
			if (text && text.trim()) {
				sendMessage(text, { voiceMode: true });
			}
		} catch (error) {
			addToast(error.message || 'Failed to transcribe audio', 'error');
		}
	};

	const { isListening, isSpeaking, error: voiceError, start: startVoiceMode, stop: stopVoiceMode } = useVoiceMode({
		onSpeechEnd: handleSpeechEnd,
	});

	useEffect(() => {
		if (voiceError) addToast(voiceError, 'error');
	}, [voiceError]);

	useEffect(() => {
		return () => {
			stopVoiceMode();
			audioPlayerRef.current?.pause();
		};
	}, [stopVoiceMode]);

	const toggleVoiceMode = () => {
		if (isListening) {
			stopVoiceMode();
			audioPlayerRef.current?.pause();
		} else {
			startVoiceMode();
		}
	};

	const handleImageSelect = (e) => {
		const file = e.target.files?.[0];
		if (!file) return;
		const reader = new FileReader();
		reader.onload = () => setPendingImage(reader.result);
		reader.readAsDataURL(file);
		e.target.value = '';
	};

	const handleKeyDown = (e) => {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			sendMessage();
		}
	};

	const status = (() => {
		if (hasError) return { label: 'ERROR', color: 'text-danger', dot: '●', blink: false };
		if (isLoading) return { label: 'THINKING', color: 'text-accent-3', dot: '●', blink: true };
		return { label: 'READY', color: 'text-success', dot: '●', blink: false };
	})();

	return (
		<>
			<Header settingsPath={`/assistants/${assistantId}/settings`}
				onBack={() => navigate(`/assistants/${assistantId}/conversations`)}
				status={status}
				counter={`MESSAGES: ${messages.filter((m) => m.role !== 'system').length}`}
				menuItems={(() => {
					const items = getAssistantMenuItems(assistantId);
					items.splice(items.length - 1, 0, {
						label: 'Memory',
						to: `/assistants/${assistantId}/conversations/${id}/memory`,
						icon: Brain,
					});
					return items;
				})()}
			>
				{isEditingTitle ? (
					<input
						ref={editInputRef}
						type="text"
						value={editTitleValue}
						onChange={(e) => setEditTitleValue(e.target.value)}
						onKeyDown={(e) => {
							if (e.key === 'Enter') {
								e.preventDefault();
								saveTitle();
							} else if (e.key === 'Escape') {
								e.preventDefault();
								cancelEditingTitle();
							}
							e.stopPropagation();
						}}
						onBlur={saveTitle}
						maxLength={100}
						className="bg-transparent border-b border-accent text-fg-2 text-sm  tracking-[0.05em] outline-none caret-accent max-w-xs"
					/>
				) : (
					<span className="flex items-center gap-2">
						<span className="text-fg-2 text-sm tracking-[0.05em] truncate max-w-xs">
							// {conversationTitle}
						</span>
						<button
							onClick={startEditingTitle}
							className="text-fg-2/30 hover:text-accent transition-colors cursor-pointer"
						>
							<Pencil size={12} />
						</button>
					</span>
				)}
			</Header>

			<div ref={scrollRef} className="flex-1 overflow-y-auto p-5 space-y-4">
				{isLoadingMore && (
					<div className="text-center text-fg-3 text-xs tracking-[0.1em] py-2">
						LOADING...
					</div>
				)}
				{messages.map((msg) => (
					<ChatMessage key={msg.id} msg={msg} assistantName={assistantName} />
				))}
				<AgentProgressIndicator assistantId={assistantId} conversationId={id} active={isLoading} />
			</div>

			{pendingImage && (
				<div className="px-5 py-2 border-t border-line-1 flex items-center gap-2">
					<img
						src={pendingImage}
						alt="Pending upload"
						className="h-16 w-16 object-cover rounded border border-line-1"
					/>
					<button
						onClick={() => setPendingImage(null)}
						className="text-danger text-xs hover:text-danger cursor-pointer"
					>
						✕
					</button>
				</div>
			)}

			<div className="px-5 py-3 border-t border-line-1 flex gap-2 items-center shrink-0">
				<span className="text-fg-3 text-xs shrink-0">USER&gt;</span>
				<input
					ref={fileInputRef}
					type="file"
					accept="image/*"
					onChange={handleImageSelect}
					className="hidden"
				/>
				<button
					onClick={() => fileInputRef.current?.click()}
					className="text-fg-3 hover:text-accent transition-colors shrink-0 cursor-pointer"
				>
					📎
				</button>
				<button
					onClick={toggleVoiceMode}
					title={isListening ? 'Stop voice mode' : 'Start voice mode'}
					className={`transition-colors shrink-0 cursor-pointer ${
						isSpeaking ? 'text-accent' : isListening ? 'text-fg-1' : 'text-fg-3 hover:text-accent'
					}`}
				>
					{isListening ? <Mic size={16} /> : <MicOff size={16} />}
				</button>
				{isListening && (
					<button
						onClick={() => {
							if (!voiceMuted) audioPlayerRef.current?.pause();
							setVoiceMuted((m) => !m);
						}}
						title={voiceMuted ? 'Unmute voice replies' : 'Mute voice replies'}
						className="text-fg-3 hover:text-accent transition-colors shrink-0 cursor-pointer"
					>
						{voiceMuted ? <VolumeX size={16} /> : <Volume2 size={16} />}
					</button>
				)}
				<input
					ref={inputRef}
					type="text"
					value={input}
					onChange={handleInputChange}
					onKeyDown={handleKeyDown}
					disabled={isLoading}
					placeholder={isLoading ? 'VERA is processing...' : 'Type something...'}
					className="flex-1 bg-transparent border-none outline-none text-fg-1  text-sm caret-accent placeholder:text-line-2"
				/>
				<button
					onClick={() => sendMessage()}
					disabled={isLoading || !input.trim()}
					className={`bg-transparent border  text-[0.7rem] px-3 py-1.5 tracking-[0.1em] transition-all shrink-0 ${
						isLoading || !input.trim()
							? 'border-line-2 text-[#2a2a3e] cursor-default'
							: 'border-line-2 text-accent cursor-pointer'
					}`}
				>
					SEND
				</button>
			</div>
		</>
	);
}
