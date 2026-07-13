import { useState, useRef, useEffect, useCallback } from 'react';
import { useParams, useNavigate, useOutletContext } from 'react-router-dom';
import { Pencil } from 'lucide-react';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import { parseEmotionFromResponse } from '../utils/parsers.js';
import ChatMessage from '../components/ChatMessage.jsx';
import Header from '../components/Header.jsx';

export default function ChatPage() {
	const { id } = useParams();
	const navigate = useNavigate();
	const {
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
	const scrollRef = useRef(null);
	const inputRef = useRef(null);
	const fileInputRef = useRef(null);
	const editInputRef = useRef(null);
	const [isEditingTitle, setIsEditingTitle] = useState(false);
	const [editTitleValue, setEditTitleValue] = useState('');

	const conversationTitle = conversations.find((c) => c.id === Number(id))?.title || '';

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
			await api.patch(route('conversations.update', { assistant: 1, id }), { title: trimmed });
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
				const res = await api.get(route('conversations.show', { assistant: 1, id }));
				if (!res.ok) {
					navigate('/conversations', { replace: true });
					return;
				}

				// Response is a flat array of messages
				const data = await res.json();
				let lastEmotion = null;

				const mapped = data.map((msg) => {
					if (msg.role === 'assistant') {
						const { emotion, text } = parseEmotionFromResponse(msg.content, emotionNames);
						lastEmotion = emotion;
						return { role: msg.role, content: text, thinking: msg.thinking, image: msg.image_url };
					}
					return { role: msg.role, content: msg.content, thinking: msg.thinking, image: msg.image_url };
				});

				setMessages(mapped);
				if (lastEmotion) setCurrentEmotion(lastEmotion);
			} catch {
				navigate('/conversations', { replace: true });
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

	const sendMessage = async () => {
		const text = input.trim();
		if ((!text && !pendingImage) || isLoading) return;

		const userMsg = {
			role: 'user',
			content: text,
			image: pendingImage || null,
		};
		const updatedMessages = [...messages, userMsg];
		setMessages([
			...updatedMessages,
			{ role: 'assistant', content: '', loading: true },
		]);
		setInput('');
		setPendingImage(null);
		setIsLoading(true);

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
					fetchEmotions(true);
				}

				const response = await api.post(route('conversations.sendMessage', { assistant: 1, id }), {
					messages: apiMessages,
				});

				if (!response.ok) {
					const errorData = await response.json().catch(() => ({}));
					throw new Error(errorData.message || 'Request failed');
				}

				const data = await response.json();
				const rawReply = data.content || '[neutral]\n...signal lost. Try again.';
				const thinking = data.thinking || null;

				const { emotion, intimate, text: cleanText } = parseEmotionFromResponse(rawReply, emotionNames);

				if (intimate !== unlocked) {
					fetchEmotions(intimate);
				}

				setCurrentEmotion(emotion);
				setHasError(false);
				setMessages([
					...updatedMessages,
					{ role: 'assistant', content: cleanText, thinking },
				]);
				setIsLoading(false);
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
			<Header
				status={status}
				counter={`MESSAGES: ${messages.filter((m) => m.role !== 'system').length}`}
				actions={
					<>
						<button
							onClick={() => navigate('/prompt')}
							className="button-primary"
						>
							PROMPT
						</button>
						<button
							onClick={() => navigate('/lorebook')}
							className="button-primary"
						>
							LOREBOOK
						</button>
						<button
							onClick={() => navigate('/providers')}
							className="button-primary"
						>
							PROVIDERS
						</button>
						<button
							onClick={() => navigate('/conversations')}
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
				{messages.map((msg, i) => (
					<ChatMessage key={i} msg={msg} />
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
				<input
					ref={inputRef}
					type="text"
					value={input}
					onChange={(e) => setInput(e.target.value)}
					onKeyDown={handleKeyDown}
					disabled={isLoading}
					placeholder={isLoading ? 'VERA is processing...' : 'Type something...'}
					className="flex-1 bg-transparent border-none outline-none text-fg-1  text-sm caret-accent placeholder:text-line-2"
				/>
				<button
					onClick={sendMessage}
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