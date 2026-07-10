import { useState, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../utils/api.js';
import Portrait from '../components/Portrait.jsx';
import Scanlines from '../components/Scanlines.jsx';
import Header from "../components/Header.jsx";

export default function LoginPage() {
	const [loginStep, setLoginStep] = useState('email');
	const [loginEmail, setLoginEmail] = useState('');
	const [input, setInput] = useState('');
	const [messages, setMessages] = useState([]);
	const inputRef = useRef(null);
	const navigate = useNavigate();

	useEffect(() => {
		api.get('/api/user').then((res) => {
			if (res.ok) navigate('/conversations', { replace: true });
		}).catch(() => {});
	}, []);

	useEffect(() => {
		if (inputRef.current) inputRef.current.focus();
	}, [loginStep]);

	const handleLogin = async () => {
		const text = input.trim();
		if (!text) return;
		setInput('');

		if (loginStep === 'email') {
			setLoginEmail(text);
			setMessages((prev) => [
				...prev,
				{ role: 'system', content: `> ${text}` },
			]);
			setLoginStep('password');
			return;
		}

		if (loginStep === 'password') {
			setMessages((prev) => [
				...prev,
				{ role: 'system', content: '> ********' },
				{ role: 'system', content: '> Authenticating...' },
			]);
			setLoginStep('authenticating');

			try {
				await api.getCsrfCookie();
				const res = await api.login(loginEmail, text);

				if (res.ok) {
					setMessages((prev) => [
						...prev,
						{ role: 'system', content: '> Access granted.' },
					]);
					setTimeout(() => navigate('/conversations', { replace: true }), 1500);
				} else {
					setMessages((prev) => [
						...prev,
						{ role: 'system', content: '> Authentication failed. Try again.' },
					]);
					setLoginStep('email');
				}
			} catch {
				setMessages((prev) => [
					...prev,
					{ role: 'system', content: '> Connection error. Try again.' },
				]);
				setLoginStep('email');
			}
		}
	};

	const handleKeyDown = (e) => {
		if (e.key === 'Enter') {
			e.preventDefault();
			handleLogin();
		}
	};

	return (
		<div className="w-full h-screen bg-bg-0 font-mono flex relative overflow-hidden">
			<Scanlines />
			<div className="absolute inset-0 pointer-events-none z-[11] viewport-ambient" />

			<div className="w-[35%] min-w-50 max-w-400 shrink-0 border-r border-line-1 relative z-5">
				<Portrait
					emotion="neutral"
					authenticated={false}
					getImageUrl={() => null}
					getVideoUrl={() => null}
				/>
			</div>

			<div className="flex-1 flex flex-col relative z-5 min-w-0">
				<Header
					status={{ label: 'OFFLINE', color: 'text-fg-3', dot: '○', blink: false }}
				/>

				<div className="flex-1 overflow-y-auto p-6">
					{messages.map((msg, i) => (
						<div key={i} className="mb-1 text-[0.8rem] text-accent">
							{msg.content}
						</div>
					))}
				</div>

				<div className="px-5 py-3 border-t border-line-1 flex gap-2 items-center shrink-0">
                    <span className="text-accent text-xs shrink-0 whitespace-nowrap">
                        {loginStep === 'password' ? '> Enter password:' : '> Enter email:'}
                    </span>
					<input
						ref={inputRef}
						type={loginStep === 'password' ? 'password' : 'text'}
						value={input}
						onChange={(e) => setInput(e.target.value)}
						onKeyDown={handleKeyDown}
						disabled={loginStep === 'authenticating'}
						className="flex-1 bg-transparent border-none outline-none text-fg-1 font-mono text-sm caret-accent placeholder:text-line-2"
						placeholder={loginStep === 'password' ? 'Enter password...' : 'Enter email...'}
					/>
				</div>
			</div>
		</div>
	);
}