import { useState, useRef, useEffect, useCallback } from 'react';
import { useParams, useNavigate, useOutletContext } from 'react-router-dom';
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

	const conversationTitle = conversations.find((c) => c.id === Number(id))?.title || '';

	// Load conversation messages
	useEffect(() => {
		const loadMessages = async () => {
			try {
				const res = await api.get(`/api/conversations/${id}/messages`);
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

				const response = await api.post(`/api/conversations/${id}/messages`, {
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
		if (hasError) return { label: 'ERROR', color: 'text-vera-red', dot: '●', blink: false };
		if (isLoading) return { label: 'THINKING', color: 'text-indigo-400', dot: '●', blink: true };
		return { label: 'READY', color: 'text-green-400', dot: '●', blink: false };
	})();

	return (
		<>
			<Header
				status={status}
				counter={`MESSAGES: ${messages.filter((m) => m.role !== 'system').length}`}
				actions={
					<>
						<button
							onClick={() => navigate('/lorebook')}
							className="bg-indigo-500/15 border border-indigo-400 text-indigo-400 hover:bg-indigo-500/25 text-[0.75rem] tracking-[0.1em] font-mono cursor-pointer transition-colors px-4 py-1.5"
						>
							LOREBOOK
						</button>
						<button
							onClick={() => navigate('/conversations')}
							className="bg-indigo-500/15 border border-indigo-400 text-indigo-400 hover:bg-indigo-500/25 text-[0.75rem] tracking-[0.1em] font-mono cursor-pointer transition-colors px-4 py-1.5"
						>
							← CONVERSATIONS
						</button>
					</>
				}
			>
				<span className="text-[#7070a0] text-sm tracking-[0.05em] truncate max-w-xs">
					// {conversationTitle}
				</span>
			</Header>

			<div ref={scrollRef} className="flex-1 overflow-y-auto p-5 space-y-4">
				{messages.map((msg, i) => (
					<ChatMessage key={i} msg={msg} />
				))}
			</div>

			{pendingImage && (
				<div className="px-5 py-2 border-t border-[#1a1a2e] flex items-center gap-2">
					<img
						src={pendingImage}
						alt="Pending upload"
						className="h-16 w-16 object-cover rounded border border-[#1a1a2e]"
					/>
					<button
						onClick={() => setPendingImage(null)}
						className="text-vera-red text-xs hover:text-red-400 cursor-pointer"
					>
						✕
					</button>
				</div>
			)}

			<div className="px-5 py-3 border-t border-[#1a1a2e] flex gap-2 items-center shrink-0">
				<span className="text-[#555568] text-xs shrink-0">USER&gt;</span>
				<input
					ref={fileInputRef}
					type="file"
					accept="image/*"
					onChange={handleImageSelect}
					className="hidden"
				/>
				<button
					onClick={() => fileInputRef.current?.click()}
					className="text-[#555568] hover:text-vera-cyan transition-colors shrink-0 cursor-pointer"
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
					className="flex-1 bg-transparent border-none outline-none text-[#a0a0b0] font-mono text-sm caret-vera-cyan placeholder:text-[#2a2a3e]"
				/>
				<button
					onClick={sendMessage}
					disabled={isLoading || !input.trim()}
					className={`bg-transparent border font-mono text-[0.7rem] px-3 py-1.5 tracking-[0.1em] transition-all shrink-0 ${
						isLoading || !input.trim()
							? 'border-[#2a2a3e] text-[#2a2a3e] cursor-default'
							: 'border-[#2a2a3e] text-vera-cyan cursor-pointer'
					}`}
				>
					SEND
				</button>
			</div>
		</>
	);
}