import { useState, useRef, useEffect, useCallback } from 'react';
import { useParams, useNavigate, useOutletContext } from 'react-router-dom';
import { Pencil, Mic, MicOff, Volume2, VolumeX } from 'lucide-react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { parseEmotionFromResponse, stripForSpeech } from '../utils/parsers.js';
import { useVoiceMode } from '../hooks/useVoiceMode.js';
import ChatMessage from '../components/ChatMessage.jsx';
import Header from '../components/Header.jsx';

export default function ChatPage() {
	const { id } = useParams();
	const navigate = useNavigate();
	const {
		assistantId,
		assistantName,
		setCurrentEmotion,
		emotionNames,
		addToast,
		fetchEmotions,
		unlocked,
		setConversations,
		conversations,
	} = useOutletContext();

	const [messages, setMessages] = useState([]);
	const [input, setInput] = useState('');
	const [isLoading, setIsLoading] = useState(false);
	const [hasError, setHasError] = useState(false);
	const [pendingImage, setPendingImage] = useState(null);
	const [hasMore, setHasMore] = useState(false);
	const [isLoadingMore, setIsLoadingMore] = useState(false);
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
	const draftKey = `chatDraft:${assistantId}:${id}`;

	// Restore draft when switching conversations
	useEffect(() => {
		setInput(localStorage.getItem(draftKey) || '');
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

	// Load conversation messages
	useEffect(() => {
		const loadMessages = async () => {
			try {
				const res = await api.get(route('conversations.show', { assistant: assistantId, id }));
				if (!res.ok) {
					navigate(`/assistants/${assistantId}/conversations`, { replace: true });
					return;
				}

				const data = await res.json();
				let lastEmotion = null;

				const mapped = data.messages.map((msg) => {
					if (msg.role === 'assistant') {
						const { emotion, text } = parseEmotionFromResponse(msg.content, emotionNames);
						lastEmotion = emotion;
						return { id: msg.id, role: msg.role, content: text, thinking: msg.thinking, image: msg.image_url };
					}
					return { id: msg.id, role: msg.role, content: msg.content, thinking: msg.thinking, image: msg.image_url };
				});

				setMessages(mapped);
				setHasMore(data.has_more);
				if (lastEmotion) setCurrentEmotion(lastEmotion);
			} catch {
				navigate(`/assistants/${assistantId}/conversations`, { replace: true });
			}
		};
		loadMessages();
	}, [id]);

	useEffect(() => {
		if (scrollRef.current) {
			scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
		}
	}, [messages]);

	useEffect(() => {
		if (inputRef.current) inputRef.current.focus();
	}, []);

	const loadOlderMessages = useCallback(async () => {
		if (isLoadingMore || !hasMore || messages.length === 0) return;

		const oldestId = messages[0].id;
		if (!oldestId) return;

		setIsLoadingMore(true);

		const scrollEl = scrollRef.current;
		const previousScrollHeight = scrollEl?.scrollHeight ?? 0;

		try {
			const url = route('conversations.show', { assistant: assistantId, id }) + `?before=${oldestId}`;
			const res = await api.get(url);
			if (!res.ok) return;

			const data = await res.json();

			const mapped = data.messages.map((msg) => {
				if (msg.role === 'assistant') {
					const { text } = parseEmotionFromResponse(msg.content, emotionNames);
					return { id: msg.id, role: msg.role, content: text, thinking: msg.thinking, image: msg.image_url };
				}
				return { id: msg.id, role: msg.role, content: msg.content, thinking: msg.thinking, image: msg.image_url };
			});

			setMessages((prev) => [...mapped, ...prev]);
			setHasMore(data.has_more);

			requestAnimationFrame(() => {
				if (scrollEl) {
					scrollEl.scrollTop = scrollEl.scrollHeight - previousScrollHeight;
				}
			});
		} catch {
			addToast('Failed to load older messages', 'error');
		} finally {
			setIsLoadingMore(false);
		}
	}, [isLoadingMore, hasMore, messages, assistantId, id, emotionNames]);

	useEffect(() => {
		const scrollEl = scrollRef.current;
		if (!scrollEl) return;

		const handleScroll = () => {
			if (scrollEl.scrollTop < 100) {
				loadOlderMessages();
			}
		};

		scrollEl.addEventListener('scroll', handleScroll);
		return () => scrollEl.removeEventListener('scroll', handleScroll);
	}, [loadOlderMessages]);

	const playSynthesizedAudio = async (rawText) => {
		const text = stripForSpeech(rawText);
		if (!text) return;

		try {
			const response = await api.post(route('voice.synthesize', { assistant: assistantId }), { text });

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

	const sendMessage = async (overrideText, { voiceMode = false } = {}) => {
		const usingOverride = overrideText !== undefined;
		const text = (usingOverride ? overrideText : input).trim();
		if ((!text && !pendingImage) || isLoading) return;

		const userMsg = {
			id: `temp-${Date.now()}`,
			role: 'user',
			content: text,
			image: pendingImage || null,
		};
		const updatedMessages = [...messages, userMsg];
		setMessages([
			...updatedMessages,
			{ role: 'assistant', content: '', loading: true },
		]);
		if (!usingOverride) setInput('');
		setPendingImage(null);
		setIsLoading(true);
		clearTimeout(draftTimeoutRef.current);
		localStorage.removeItem(draftKey);

		// Build API message format
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
				const creatorTrigger = '[creator mode: "tsuru tuneado"]';
				if (text.toLowerCase().includes(creatorTrigger.toLowerCase())) {
					fetchEmotions(assistantId);
				}

				const response = await api.post(route('conversations.sendMessage', { assistant: assistantId, id }), {
					messages: apiMessages,
					...(voiceMode ? { voice_mode: true } : {}),
				});

				if (!response.ok) {
					const errorData = await response.json().catch(() => ({}));
					throw new Error(errorData.message || 'Request failed');
				}

				const data = await response.json();
				const rawReply = data.content || '[default]\n...signal lost. Try again.';
				const thinking = data.thinking || null;

				const { emotion, intimate, text: cleanText } = parseEmotionFromResponse(rawReply, emotionNames);

				if (intimate !== unlocked) {
					fetchEmotions(assistantId);
				}

				setCurrentEmotion(emotion);
				setHasError(false);
				setMessages([
					...updatedMessages,
					{ id: `temp-${Date.now()}-reply`, role: 'assistant', content: cleanText, thinking },
				]);
				setIsLoading(false);
				if (voiceMode && !voiceMuted) {
					playSynthesizedAudio(cleanText);
				}
				return;
			} catch (error) {
				lastError = error;
				setHasError(true);

				const msg = error.message?.toLowerCase() || '';
				const isTimeout = msg.includes('timeout') ||
					msg.includes('execution time') ||
					msg.includes('502') ||
					msg.includes('504');

				if (isTimeout && attempt < maxRetries) {
					addToast(`Signal interference. Retrying... (${attempt}/${maxRetries})`, 'error');
					await new Promise((r) => setTimeout(r, 2000));
					continue;
				}
				break;
			}
		}

		addToast(lastError?.message || 'Connection to The Bridge failed', 'error');
		setMessages([...updatedMessages]);
		setIsLoading(false);
	};

	const handleSpeechEnd = useCallback(async (audioBlob) => {
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
	}, [assistantId, sendMessage, addToast]);

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
				status={status}
				counter={`MESSAGES: ${messages.filter((m) => m.role !== 'system').length}`}
				actions={
					<>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/prompt`)}
							className="button-primary"
						>
							PROMPT
						</button>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/archive`)}
							className="button-primary"
						>
							ARCHIVE
						</button>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/providers`)}
							className="button-primary"
						>
							PROVIDERS
						</button>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/voice`)}
							className="button-primary"
						>
							VOICE
						</button>
						<button
							onClick={() => navigate(`/assistants/${assistantId}/conversations`)}
							className="button-primary"
						>
							← CONVERSATIONS
						</button>
					</>
				}
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